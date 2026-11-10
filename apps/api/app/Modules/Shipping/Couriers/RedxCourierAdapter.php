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
 * REDX Open API (`v1.0.0-beta`) Adapter.
 */
class RedxCourierAdapter implements CourierProvider
{
    public function __construct(
        protected CourierAuditLogger $auditLogger
    ) {}

    public function providerName(): string
    {
        return 'redx';
    }

    public function calculateDeliveryCharge(DeliveryQuoteRequest $request): DeliveryChargeResult
    {
        $isInsideDhaka = strcasecmp(trim($request->recipientDivision), 'Dhaka') === 0
            && strcasecmp(trim($request->recipientDistrict), 'Dhaka') === 0;

        $deliveryChargePaisa = $isInsideDhaka ? 6500 : 12000;
        $codFeePaisa = $request->codAmountPaisa > 0
            ? (int) round($request->codAmountPaisa * 0.01)
            : 0;

        return new DeliveryChargeResult(
            successful: true,
            courier: 'redx',
            deliveryChargePaisa: $deliveryChargePaisa,
            codFeePaisa: $codFeePaisa,
            totalChargePaisa: $deliveryChargePaisa + $codFeePaisa,
            currency: 'BDT',
            estimatedDays: $isInsideDhaka ? 2 : 4
        );
    }

    public function createShipment(CreateShipmentRequest $request): CreateShipmentResult
    {
        $cfg = config('shipping.couriers.redx');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/parcel";

        $payload = [
            'customer_name'          => $request->recipientName,
            'customer_phone'         => $request->recipientPhone,
            'customer_address'       => $request->recipientAddress,
            'delivery_area'          => $request->recipientDistrict,
            'delivery_area_id'       => $request->recipientZoneId ?? 1,
            'merchant_invoice_id'    => $request->merchantOrderReference,
            'cash_collection_amount' => (string) $request->codAmountBdtInt(),
            'parcel_weight'          => $request->weightGrams,
            'instruction'            => (string) ($request->specialInstruction ?? ''),
            'value'                  => (int) round($request->declaredValuePaisa / 100),
        ];

        try {
            $response = Http::timeout((int) config('shipping.http_timeout_sec', 15))
                ->withHeaders([
                    'API-ACCESS-TOKEN' => 'Bearer ' . (string) ($cfg['access_token'] ?? ''),
                ])
                ->acceptJson()
                ->post($endpoint, $payload);

            $body = $response->json() ?? [];
            $trackingId = isset($body['tracking_id']) ? (string) $body['tracking_id'] : null;
            $isSuccess = $response->successful() && $trackingId !== null && $trackingId !== '';

            $this->auditLogger->log(
                courier: 'redx',
                operation: 'create_shipment',
                httpMethod: 'POST',
                endpoint: $endpoint,
                responseStatus: $response->status(),
                requestPayload: $payload,
                responsePayload: $body,
                isError: !$isSuccess,
                errorMessage: $isSuccess ? null : (string) ($body['message'] ?? 'REDX parcel creation failed.'),
                orderId: $request->orderId
            );

            if (!$isSuccess) {
                return new CreateShipmentResult(
                    successful: false,
                    courier: 'redx',
                    consignmentId: null,
                    trackingNumber: null,
                    trackingUrl: null,
                    externalStatus: 'failed',
                    internalStatus: 'failed',
                    rawRequest: SensitiveDataRedactor::redact($payload),
                    rawResponse: SensitiveDataRedactor::redact($body),
                    errorMessage: (string) ($body['message'] ?? 'REDX parcel creation failed.')
                );
            }

            $trackingBase = (string) ($cfg['tracking_base'] ?? 'https://redx.com.bd/track-global-parcel/?trackingId=');

            return new CreateShipmentResult(
                successful: true,
                courier: 'redx',
                consignmentId: $trackingId,
                trackingNumber: $trackingId,
                trackingUrl: $trackingBase . urlencode($trackingId),
                externalStatus: 'pickup-pending',
                internalStatus: 'created',
                rawRequest: SensitiveDataRedactor::redact($payload),
                rawResponse: SensitiveDataRedactor::redact($body)
            );
        } catch (Throwable $e) {
            $this->auditLogger->log(
                courier: 'redx',
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
                courier: 'redx',
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
            courier: 'redx',
            consignmentId: $consignmentId,
            externalStatus: 'cancelled',
            internalStatus: 'cancelled',
            rawRequest: ['tracking_id' => $consignmentId, 'reason' => $reason]
        );
    }

    public function trackShipment(string $consignmentId): ShipmentTrackingResult
    {
        $cfg = config('shipping.couriers.redx');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/parcel/track/{$consignmentId}";

        $response = Http::timeout((int) config('shipping.http_timeout_sec', 15))
            ->withHeaders([
                'API-ACCESS-TOKEN' => 'Bearer ' . (string) ($cfg['access_token'] ?? ''),
            ])
            ->acceptJson()
            ->get($endpoint);

        $body = $response->json() ?? [];
        $trackingList = $body['tracking'] ?? [];
        $latest = is_array($trackingList) && !empty($trackingList) ? end($trackingList) : [];
        $externalStatus = (string) ($latest['status'] ?? $body['status'] ?? '');
        $isSuccess = $response->successful() && $externalStatus !== '';

        $this->auditLogger->log(
            courier: 'redx',
            operation: 'track_shipment',
            httpMethod: 'GET',
            endpoint: $endpoint,
            responseStatus: $response->status(),
            requestPayload: ['tracking_id' => $consignmentId],
            responsePayload: $body,
            isError: !$isSuccess,
            errorMessage: $isSuccess ? null : 'REDX tracking lookup failed.'
        );

        return new ShipmentTrackingResult(
            successful: $isSuccess,
            courier: 'redx',
            consignmentId: $consignmentId,
            externalStatus: $externalStatus !== '' ? $externalStatus : 'unknown',
            internalStatus: $this->mapExternalStatus($externalStatus),
            description: (string) ($latest['message_en'] ?? "REDX status: {$externalStatus}"),
            rawRequest: ['tracking_id' => $consignmentId],
            rawResponse: SensitiveDataRedactor::redact($body),
            errorMessage: $isSuccess ? null : 'REDX tracking lookup failed.'
        );
    }

    public function parseTrackingWebhook(
        array $payload,
        array $headers = [],
        string $rawBody = ''
    ): TrackingWebhookPayload {
        $cfg = config('shipping.couriers.redx');
        $secret = (string) ($cfg['webhook_secret'] ?? '');

        if ($secret !== '') {
            $provided = (string) ($headers['x-redx-signature'][0] ?? $headers['x-signature'][0] ?? '');
            $expectedHmac = hash_hmac('sha256', $rawBody !== '' ? $rawBody : (string) json_encode($payload), $secret);
            if ($provided === '' || (!hash_equals($secret, $provided) && !hash_equals($expectedHmac, $provided))) {
                throw new CourierWebhookSignatureException('Invalid REDX webhook signature.');
            }
        }

        $consignmentId = (string) ($payload['tracking_number'] ?? $payload['tracking_id'] ?? '');
        $externalStatus = (string) ($payload['status'] ?? 'in-transit');
        $internalStatus = $this->mapExternalStatus($externalStatus);
        $timestamp = (string) ($payload['timestamp'] ?? '');
        $collectedPaisa = isset($payload['cash_collection_amount']) && $internalStatus === 'delivered'
            ? (int) round(((float) $payload['cash_collection_amount']) * 100)
            : null;

        return new TrackingWebhookPayload(
            courier: 'redx',
            consignmentId: $consignmentId,
            merchantOrderReference: isset($payload['invoice_number']) ? (string) $payload['invoice_number'] : null,
            eventKey: 'redx-' . $consignmentId . '-' . strtolower($externalStatus) . '-' . $timestamp,
            externalStatus: $externalStatus,
            internalStatus: $internalStatus,
            description: (string) ($payload['message_en'] ?? "REDX webhook: {$externalStatus}"),
            collectedCodAmountPaisa: $collectedPaisa,
            occurredAt: $timestamp !== '' ? $timestamp : now()->toIso8601String(),
            rawPayload: SensitiveDataRedactor::redact($payload)
        );
    }

    public function mapExternalStatus(string $externalStatus): string
    {
        $normalized = strtolower(trim(str_replace(['_', ' '], '-', $externalStatus)));

        return match ($normalized) {
            'pickup-pending' => 'created',
            'parcel-picked-up', 'picked-up' => 'picked_up',
            'in-transit' => 'in_transit',
            'out-for-delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'agent-hold', 'delivery-failed', 'hold' => 'failed',
            'returning', 'return-in-progress' => 'returning',
            'returned', 'agent-area-change' => 'returned',
            'cancelled' => 'cancelled',
            default => 'in_transit',
        };
    }
}

