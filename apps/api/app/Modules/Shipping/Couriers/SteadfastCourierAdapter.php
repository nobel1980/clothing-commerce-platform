<?php

namespace App\Modules\Shipping\Couriers;

use App\Modules\Shipping\Services\CourierAuditLogger;
use App\Shared\Contracts\Shipping\CancelShipmentResult;
use App\Shared\Contracts\Shipping\CourierProvider;
use App\Shared\Contracts\Shipping\CreateShipmentRequest;
use App\Shared\Contracts\Shipping\CreateShipmentResult;
use App\Shared\Contracts\Shipping\DeliveryChargeResult;
use App\Shared\Contracts\Shipping\DeliveryQuoteRequest;
use App\Shared\Contracts\Shipping\ShipmentTrackingResult;
use App\Shared\Contracts\Shipping\TrackingWebhookPayload;
use App\Shared\Exceptions\Shipping\CourierWebhookSignatureException;
use App\Shared\Support\SensitiveDataRedactor;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Steadfast Courier Packzy API (v1) Adapter (`https://portal.packzy.com/api/v1`).
 */
class SteadfastCourierAdapter implements CourierProvider
{
    public function __construct(
        protected CourierAuditLogger $auditLogger
    ) {}

    public function providerName(): string
    {
        return 'steadfast';
    }

    public function calculateDeliveryCharge(DeliveryQuoteRequest $request): DeliveryChargeResult
    {
        $isInsideDhaka = strcasecmp(trim($request->recipientDivision), 'Dhaka') === 0
            && strcasecmp(trim($request->recipientDistrict), 'Dhaka') === 0;
        $isDhakaSub = strcasecmp(trim($request->recipientDivision), 'Dhaka') === 0 && !$isInsideDhaka;

        $basePaisa = match (true) {
            $isInsideDhaka => 7000,  // 70.00 BDT
            $isDhakaSub    => 10000, // 100.00 BDT
            default        => 13000, // 130.00 BDT
        };

        $extraKg = max(0, (int) ceil($request->weightKgDecimal() - 1.0));
        $weightSurchargePaisa = $extraKg * 2000; // 20.00 BDT per additional kg
        $deliveryChargePaisa = $basePaisa + $weightSurchargePaisa;
        $codFeePaisa = $request->codAmountPaisa > 0
            ? (int) round($request->codAmountPaisa * 0.01) // 1% COD fee
            : 0;

        return new DeliveryChargeResult(
            successful: true,
            courier: 'steadfast',
            deliveryChargePaisa: $deliveryChargePaisa,
            codFeePaisa: $codFeePaisa,
            totalChargePaisa: $deliveryChargePaisa + $codFeePaisa,
            currency: 'BDT',
            estimatedDays: $isInsideDhaka ? 2 : 3
        );
    }

    public function createShipment(CreateShipmentRequest $request): CreateShipmentResult
    {
        $cfg = config('shipping.couriers.steadfast');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/create_order";

        $payload = [
            'invoice'           => $request->merchantOrderReference,
            'recipient_name'    => $request->recipientName,
            'recipient_phone'   => $request->recipientPhone,
            'recipient_address' => "{$request->recipientAddress}, {$request->recipientDistrict}, {$request->recipientDivision}",
            'cod_amount'        => $request->codAmountBdtInt(),
            'note'              => (string) ($request->specialInstruction ?? ''),
        ];

        try {
            $response = Http::timeout((int) config('shipping.http_timeout_sec', 15))
                ->withHeaders([
                    'Api-Key'    => (string) ($cfg['api_key'] ?? ''),
                    'Secret-Key' => (string) ($cfg['secret_key'] ?? ''),
                ])
                ->acceptJson()
                ->post($endpoint, $payload);

            $body = $response->json() ?? [];
            $consignment = $body['consignment'] ?? [];
            $consignmentId = isset($consignment['consignment_id']) ? (string) $consignment['consignment_id'] : null;
            $trackingCode = isset($consignment['tracking_code']) ? (string) $consignment['tracking_code'] : $consignmentId;
            $isSuccess = $response->successful() && ($body['status'] ?? 0) == 200 && $consignmentId !== null;

            $this->auditLogger->log(
                courier: 'steadfast',
                operation: 'create_shipment',
                httpMethod: 'POST',
                endpoint: $endpoint,
                responseStatus: $response->status(),
                requestPayload: $payload,
                responsePayload: $body,
                isError: !$isSuccess,
                errorMessage: $isSuccess ? null : (string) ($body['message'] ?? 'Steadfast create_order failed.'),
                orderId: $request->orderId
            );

            if (!$isSuccess) {
                return new CreateShipmentResult(
                    successful: false,
                    courier: 'steadfast',
                    consignmentId: null,
                    trackingNumber: null,
                    trackingUrl: null,
                    externalStatus: 'failed',
                    internalStatus: 'failed',
                    rawRequest: SensitiveDataRedactor::redact($payload),
                    rawResponse: SensitiveDataRedactor::redact($body),
                    errorMessage: (string) ($body['message'] ?? 'Steadfast create_order failed.')
                );
            }

            $externalStatus = (string) ($consignment['status'] ?? 'in_review');
            $trackingBase = (string) ($cfg['tracking_base'] ?? 'https://steadfast.com.bd/t/');

            return new CreateShipmentResult(
                successful: true,
                courier: 'steadfast',
                consignmentId: $consignmentId,
                trackingNumber: $trackingCode,
                trackingUrl: $trackingBase . urlencode((string) $trackingCode),
                externalStatus: $externalStatus,
                internalStatus: $this->mapExternalStatus($externalStatus),
                rawRequest: SensitiveDataRedactor::redact($payload),
                rawResponse: SensitiveDataRedactor::redact($body)
            );
        } catch (Throwable $e) {
            $this->auditLogger->log(
                courier: 'steadfast',
                operation: 'create_shipment',
                httpMethod: 'POST',
                endpoint: $endpoint,
                responseStatus: null,
                requestPayload: $payload,
                responsePayload: ['exception' => $e->getMessage()],
                isError: true,
                errorMessage: $e->getMessage(),
                orderId: $request->orderId
            );

            return new CreateShipmentResult(
                successful: false,
                courier: 'steadfast',
                consignmentId: null,
                trackingNumber: null,
                trackingUrl: null,
                externalStatus: 'error',
                internalStatus: 'failed',
                rawRequest: SensitiveDataRedactor::redact($payload),
                rawResponse: ['exception' => $e->getMessage()],
                errorMessage: $e->getMessage()
            );
        }
    }

    public function cancelShipment(string $consignmentId, ?string $reason = null): CancelShipmentResult
    {
        return new CancelShipmentResult(
            successful: true,
            courier: 'steadfast',
            consignmentId: $consignmentId,
            externalStatus: 'cancelled',
            internalStatus: 'cancelled',
            rawRequest: ['consignment_id' => $consignmentId, 'reason' => $reason]
        );
    }

    public function trackShipment(string $consignmentId): ShipmentTrackingResult
    {
        $cfg = config('shipping.couriers.steadfast');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/status_by_cid/{$consignmentId}";

        $response = Http::timeout((int) config('shipping.http_timeout_sec', 15))
            ->withHeaders([
                'Api-Key'    => (string) ($cfg['api_key'] ?? ''),
                'Secret-Key' => (string) ($cfg['secret_key'] ?? ''),
            ])
            ->acceptJson()
            ->get($endpoint);

        $body = $response->json() ?? [];
        $externalStatus = (string) ($body['delivery_status'] ?? '');
        $isSuccess = $response->successful() && $externalStatus !== '';

        $this->auditLogger->log(
            courier: 'steadfast',
            operation: 'track_shipment',
            httpMethod: 'GET',
            endpoint: $endpoint,
            responseStatus: $response->status(),
            requestPayload: ['consignment_id' => $consignmentId],
            responsePayload: $body,
            isError: !$isSuccess,
            errorMessage: $isSuccess ? null : 'Steadfast tracking lookup failed.'
        );

        $internalStatus = $this->mapExternalStatus($externalStatus);

        return new ShipmentTrackingResult(
            successful: $isSuccess,
            courier: 'steadfast',
            consignmentId: $consignmentId,
            externalStatus: $externalStatus !== '' ? $externalStatus : 'unknown',
            internalStatus: $internalStatus,
            description: "Steadfast status: {$externalStatus}",
            rawRequest: ['consignment_id' => $consignmentId],
            rawResponse: SensitiveDataRedactor::redact($body),
            errorMessage: $isSuccess ? null : 'Steadfast tracking lookup failed.'
        );
    }

    public function parseTrackingWebhook(
        array $payload,
        array $headers = [],
        string $rawBody = ''
    ): TrackingWebhookPayload {
        $cfg = config('shipping.couriers.steadfast');
        $secret = (string) ($cfg['webhook_secret'] ?? '');

        if ($secret !== '') {
            $provided = (string) ($headers['x-steadfast-signature'][0] ?? $headers['authorization'][0] ?? '');
            $expectedHmac = hash_hmac('sha256', $rawBody !== '' ? $rawBody : (string) json_encode($payload), $secret);
            if ($provided === '' || (!hash_equals($secret, str_replace('Bearer ', '', $provided)) && !hash_equals($expectedHmac, $provided))) {
                throw new CourierWebhookSignatureException('Invalid Steadfast webhook signature.');
            }
        }

        $consignmentId = (string) ($payload['consignment_id'] ?? '');
        $externalStatus = (string) ($payload['status'] ?? 'updated');
        $internalStatus = $this->mapExternalStatus($externalStatus);
        $updatedAt = (string) ($payload['updated_at'] ?? '');
        $collectedCodPaisa = isset($payload['cod_amount'])
            ? (int) round(((float) $payload['cod_amount']) * 100)
            : null;

        return new TrackingWebhookPayload(
            courier: 'steadfast',
            consignmentId: $consignmentId,
            merchantOrderReference: isset($payload['invoice']) ? (string) $payload['invoice'] : null,
            eventKey: 'steadfast-' . $consignmentId . '-' . strtolower($externalStatus) . '-' . $updatedAt,
            externalStatus: $externalStatus,
            internalStatus: $internalStatus,
            description: (string) ($payload['tracking_message'] ?? "Steadfast status: {$externalStatus}"),
            collectedCodAmountPaisa: $collectedCodPaisa,
            occurredAt: $updatedAt !== '' ? $updatedAt : now()->toIso8601String(),
            rawPayload: SensitiveDataRedactor::redact($payload)
        );
    }

    public function mapExternalStatus(string $externalStatus): string
    {
        $normalized = strtolower(trim(str_replace(['-', ' '], '_', $externalStatus)));

        return match ($normalized) {
            'in_review', 'pending' => 'created',
            'picked_up' => 'picked_up',
            'in_transit' => 'in_transit',
            'delivered', 'delivered_approval_pending', 'partial_delivered', 'partial_delivered_approval_pending' => 'delivered',
            'hold', 'unknown', 'unknown_approval_pending' => 'failed',
            'returned', 'returned_approval_pending' => 'returned',
            'cancelled', 'cancelled_approval_pending' => 'cancelled',
            default => 'in_transit',
        };
    }
}

