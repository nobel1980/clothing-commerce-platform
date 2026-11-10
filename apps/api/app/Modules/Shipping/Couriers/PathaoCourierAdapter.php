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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Official Pathao Hermes Merchant API (v1) Adapter.
 *
 * Endpoints:
 * - Issue Token:      POST /aladdin/api/v1/issue-token
 * - Price Calculation: POST /aladdin/api/v1/merchant/price-plan
 * - Create Order:     POST /aladdin/api/v1/orders
 * - Order Details:    GET  /aladdin/api/v1/orders/{consignment_id}
 * - Cancel Order:     POST /aladdin/api/v1/orders/{consignment_id}/cancel
 */
class PathaoCourierAdapter implements CourierProvider
{
    private const CITY_MAP = [
        'dhaka'      => 1,
        'chattogram' => 2,
        'chittagong' => 2,
        'sylhet'     => 3,
        'rajshahi'   => 4,
        'khulna'     => 5,
        'barishal'   => 6,
        'barisal'    => 6,
        'rangpur'    => 7,
        'mymensingh' => 8,
    ];

    public function __construct(
        protected CourierAuditLogger $auditLogger
    ) {}

    public function providerName(): string
    {
        return 'pathao';
    }

    public function calculateDeliveryCharge(DeliveryQuoteRequest $request): DeliveryChargeResult
    {
        $cfg = config('shipping.couriers.pathao');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/aladdin/api/v1/merchant/price-plan";

        $cityId = $request->recipientCityId ?? $this->resolveCityId($request->recipientDistrict, $request->recipientDivision);
        $zoneId = $request->recipientZoneId ?? 1;
        $deliveryType = $request->deliveryType === 'express' ? 12 : 48;

        $payload = [
            'store_id'       => (int) ($cfg['store_id'] ?? 1),
            'item_type'      => 2, // 2 = Parcel
            'delivery_type'  => $deliveryType,
            'item_weight'    => $request->weightKgDecimal(),
            'recipient_city' => $cityId,
            'recipient_zone' => $zoneId,
        ];

        $attempts = 0;

        try {
            $token = $this->getAccessToken();
            $response = $this->sendWithRetry(
                method: 'POST',
                url: $endpoint,
                token: $token,
                payload: $payload,
                attempts: $attempts
            );

            $body = $response->json() ?? [];
            $isSuccess = $response->successful() && isset($body['data']['final_price']);

            $this->auditLogger->log(
                courier: 'pathao',
                operation: 'calculate_charge',
                httpMethod: 'POST',
                endpoint: $endpoint,
                responseStatus: $response->status(),
                requestPayload: $payload,
                responsePayload: $body,
                attempts: $attempts,
                isError: !$isSuccess,
                errorMessage: $isSuccess ? null : (string) ($body['message'] ?? 'Pathao price-plan calculation failed.')
            );

            if (!$isSuccess) {
                return new DeliveryChargeResult(
                    successful: false,
                    courier: 'pathao',
                    deliveryChargePaisa: 0,
                    rawRequest: SensitiveDataRedactor::redact($payload),
                    rawResponse: SensitiveDataRedactor::redact($body),
                    errorMessage: (string) ($body['message'] ?? 'Pathao price-plan calculation failed.')
                );
            }

            $data = $body['data'];
            $finalPriceBdt = (float) ($data['final_price'] ?? $data['price'] ?? 0);
            $deliveryChargePaisa = (int) round($finalPriceBdt * 100);

            $codPercentage = (float) ($data['cod_percentage'] ?? 0);
            $codFeePaisa = $request->codAmountPaisa > 0 && $codPercentage > 0
                ? (int) round($request->codAmountPaisa * $codPercentage)
                : 0;

            return new DeliveryChargeResult(
                successful: true,
                courier: 'pathao',
                deliveryChargePaisa: $deliveryChargePaisa,
                codFeePaisa: $codFeePaisa,
                totalChargePaisa: $deliveryChargePaisa + $codFeePaisa,
                currency: 'BDT',
                estimatedDays: $deliveryType === 12 ? 1 : ($cityId === 1 ? 2 : 3),
                rawRequest: SensitiveDataRedactor::redact($payload),
                rawResponse: SensitiveDataRedactor::redact($body)
            );
        } catch (Throwable $e) {
            $this->auditLogger->log(
                courier: 'pathao',
                operation: 'calculate_charge',
                httpMethod: 'POST',
                endpoint: $endpoint,
                responseStatus: null,
                requestPayload: $payload,
                responsePayload: ['exception' => $e->getMessage()],
                attempts: max(1, $attempts),
                isError: true,
                errorMessage: $e->getMessage()
            );

            return new DeliveryChargeResult(
                successful: false,
                courier: 'pathao',
                deliveryChargePaisa: 0,
                rawRequest: SensitiveDataRedactor::redact($payload),
                rawResponse: ['exception' => $e->getMessage()],
                errorMessage: $e->getMessage()
            );
        }
    }

    public function createShipment(CreateShipmentRequest $request): CreateShipmentResult
    {
        $cfg = config('shipping.couriers.pathao');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/aladdin/api/v1/orders";

        $cityId = $request->recipientCityId ?? $this->resolveCityId($request->recipientDistrict, $request->recipientDivision);
        $zoneId = $request->recipientZoneId ?? 1;
        $deliveryType = $request->deliveryType === 'express' ? 12 : 48;

        // Pathao requires recipient_address to be at least 10 characters
        $addressLine = trim($request->recipientAddress);
        if (strlen($addressLine) < 10) {
            $addressLine = "{$addressLine}, {$request->recipientDistrict}, {$request->recipientDivision}";
        }

        $payload = [
            'store_id'            => (int) ($cfg['store_id'] ?? 1),
            'merchant_order_id'   => $request->merchantOrderReference,
            'recipient_name'      => $request->recipientName,
            'recipient_phone'     => $this->normalizeBdPhone($request->recipientPhone),
            'recipient_address'   => $addressLine,
            'recipient_city'      => $cityId,
            'recipient_zone'      => $zoneId,
            'delivery_type'       => $deliveryType,
            'item_type'           => 2, // 2 = Parcel
            'special_instruction' => (string) ($request->specialInstruction ?? ''),
            'item_quantity'       => max(1, $request->itemQuantity),
            'item_weight'         => $request->weightKg(),
            'amount_to_collect'   => $request->codAmountBdtInt(),
            'item_description'    => $request->itemDescription,
        ];

        if ($request->recipientAreaId !== null) {
            $payload['recipient_area'] = $request->recipientAreaId;
        }

        $attempts = 0;

        try {
            $token = $this->getAccessToken($request->orderId);
            $response = $this->sendWithRetry(
                method: 'POST',
                url: $endpoint,
                token: $token,
                payload: $payload,
                attempts: $attempts
            );

            $body = $response->json() ?? [];
            $consignmentId = isset($body['data']['consignment_id'])
                ? (string) $body['data']['consignment_id']
                : null;
            $isSuccess = $response->successful() && $consignmentId !== null && $consignmentId !== '';

            $this->auditLogger->log(
                courier: 'pathao',
                operation: 'create_shipment',
                httpMethod: 'POST',
                endpoint: $endpoint,
                responseStatus: $response->status(),
                requestPayload: $payload,
                responsePayload: $body,
                attempts: $attempts,
                isError: !$isSuccess,
                errorMessage: $isSuccess ? null : (string) ($body['message'] ?? 'Pathao consignment creation failed.'),
                orderId: $request->orderId
            );

            if (!$isSuccess) {
                return new CreateShipmentResult(
                    successful: false,
                    courier: 'pathao',
                    consignmentId: null,
                    trackingNumber: null,
                    trackingUrl: null,
                    externalStatus: (string) ($body['data']['order_status'] ?? 'Failed'),
                    internalStatus: 'failed',
                    rawRequest: SensitiveDataRedactor::redact($payload),
                    rawResponse: SensitiveDataRedactor::redact($body),
                    errorMessage: (string) ($body['message'] ?? 'Pathao consignment creation failed.')
                );
            }

            $externalStatus = (string) ($body['data']['order_status'] ?? 'Pending');
            $deliveryFeeBdt = (float) ($body['data']['delivery_fee'] ?? 0);
            $shippingChargePaisa = (int) round($deliveryFeeBdt * 100);
            $trackingBase = (string) ($cfg['tracking_base'] ?? 'https://merchant.pathao.com/tracking?consignment_id=');

            return new CreateShipmentResult(
                successful: true,
                courier: 'pathao',
                consignmentId: $consignmentId,
                trackingNumber: $consignmentId,
                trackingUrl: $trackingBase . urlencode($consignmentId),
                externalStatus: $externalStatus,
                internalStatus: $this->mapExternalStatus($externalStatus),
                shippingChargePaisa: $shippingChargePaisa,
                rawRequest: SensitiveDataRedactor::redact($payload),
                rawResponse: SensitiveDataRedactor::redact($body)
            );
        } catch (Throwable $e) {
            $this->auditLogger->log(
                courier: 'pathao',
                operation: 'create_shipment',
                httpMethod: 'POST',
                endpoint: $endpoint,
                responseStatus: null,
                requestPayload: $payload,
                responsePayload: ['exception' => $e->getMessage()],
                attempts: max(1, $attempts),
                isError: true,
                errorMessage: $e->getMessage(),
                orderId: $request->orderId
            );

            return new CreateShipmentResult(
                successful: false,
                courier: 'pathao',
                consignmentId: null,
                trackingNumber: null,
                trackingUrl: null,
                externalStatus: 'Error',
                internalStatus: 'failed',
                rawRequest: SensitiveDataRedactor::redact($payload),
                rawResponse: ['exception' => $e->getMessage()],
                errorMessage: $e->getMessage()
            );
        }
    }

    public function cancelShipment(string $consignmentId, ?string $reason = null): CancelShipmentResult
    {
        $cfg = config('shipping.couriers.pathao');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/aladdin/api/v1/orders/{$consignmentId}/cancel";
        $payload = array_filter(['reason' => $reason]);
        $attempts = 0;

        try {
            $token = $this->getAccessToken();
            $response = $this->sendWithRetry(
                method: 'POST',
                url: $endpoint,
                token: $token,
                payload: $payload,
                attempts: $attempts
            );

            $body = $response->json() ?? [];
            $isSuccess = $response->successful();

            $this->auditLogger->log(
                courier: 'pathao',
                operation: 'cancel_shipment',
                httpMethod: 'POST',
                endpoint: $endpoint,
                responseStatus: $response->status(),
                requestPayload: ['consignment_id' => $consignmentId, ...$payload],
                responsePayload: $body,
                attempts: $attempts,
                isError: !$isSuccess,
                errorMessage: $isSuccess ? null : (string) ($body['message'] ?? 'Pathao cancellation failed.')
            );

            return new CancelShipmentResult(
                successful: $isSuccess,
                courier: 'pathao',
                consignmentId: $consignmentId,
                externalStatus: $isSuccess ? 'Cancelled' : 'Cancel_Failed',
                internalStatus: $isSuccess ? 'cancelled' : 'failed',
                rawRequest: ['consignment_id' => $consignmentId, ...$payload],
                rawResponse: SensitiveDataRedactor::redact($body),
                errorMessage: $isSuccess ? null : (string) ($body['message'] ?? 'Pathao cancellation failed.')
            );
        } catch (Throwable $e) {
            $this->auditLogger->log(
                courier: 'pathao',
                operation: 'cancel_shipment',
                httpMethod: 'POST',
                endpoint: $endpoint,
                responseStatus: null,
                requestPayload: ['consignment_id' => $consignmentId],
                responsePayload: ['exception' => $e->getMessage()],
                attempts: max(1, $attempts),
                isError: true,
                errorMessage: $e->getMessage()
            );

            return new CancelShipmentResult(
                successful: false,
                courier: 'pathao',
                consignmentId: $consignmentId,
                externalStatus: 'Error',
                internalStatus: 'failed',
                rawRequest: ['consignment_id' => $consignmentId],
                rawResponse: ['exception' => $e->getMessage()],
                errorMessage: $e->getMessage()
            );
        }
    }

    public function trackShipment(string $consignmentId): ShipmentTrackingResult
    {
        $cfg = config('shipping.couriers.pathao');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/aladdin/api/v1/orders/{$consignmentId}";
        $attempts = 0;

        try {
            $token = $this->getAccessToken();
            $response = $this->sendWithRetry(
                method: 'GET',
                url: $endpoint,
                token: $token,
                payload: [],
                attempts: $attempts
            );

            $body = $response->json() ?? [];
            $data = $body['data'] ?? $body;
            $externalStatus = (string) ($data['order_status'] ?? $data['order_status_slug'] ?? '');
            $isSuccess = $response->successful() && $externalStatus !== '';

            $this->auditLogger->log(
                courier: 'pathao',
                operation: 'track_shipment',
                httpMethod: 'GET',
                endpoint: $endpoint,
                responseStatus: $response->status(),
                requestPayload: ['consignment_id' => $consignmentId],
                responsePayload: $body,
                attempts: $attempts,
                isError: !$isSuccess,
                errorMessage: $isSuccess ? null : (string) ($body['message'] ?? 'Pathao tracking lookup failed.')
            );

            if (!$isSuccess) {
                return new ShipmentTrackingResult(
                    successful: false,
                    courier: 'pathao',
                    consignmentId: $consignmentId,
                    externalStatus: 'Unknown',
                    internalStatus: 'in_transit',
                    rawRequest: ['consignment_id' => $consignmentId],
                    rawResponse: SensitiveDataRedactor::redact($body),
                    errorMessage: (string) ($body['message'] ?? 'Pathao tracking lookup failed.')
                );
            }

            $internalStatus = $this->mapExternalStatus($externalStatus);
            $collectedPaisa = isset($data['collected_amount'])
                ? (int) round(((float) $data['collected_amount']) * 100)
                : (isset($data['amount_to_collect']) && $internalStatus === 'delivered'
                    ? (int) round(((float) $data['amount_to_collect']) * 100)
                    : null);

            $location = isset($data['city_name']) ? (string) $data['city_name'] : null;
            $occurredAt = isset($data['updated_at']) ? (string) $data['updated_at'] : now()->toIso8601String();

            return new ShipmentTrackingResult(
                successful: true,
                courier: 'pathao',
                consignmentId: $consignmentId,
                externalStatus: $externalStatus,
                internalStatus: $internalStatus,
                description: (string) ($data['order_status_text'] ?? "Pathao status: {$externalStatus}"),
                location: $location,
                collectedCodAmountPaisa: $collectedPaisa,
                events: [
                    [
                        'external_status' => $externalStatus,
                        'internal_status' => $internalStatus,
                        'description'     => (string) ($data['order_status_text'] ?? "Pathao status: {$externalStatus}"),
                        'location'        => $location,
                        'occurred_at'     => $occurredAt,
                    ],
                ],
                rawRequest: ['consignment_id' => $consignmentId],
                rawResponse: SensitiveDataRedactor::redact($body)
            );
        } catch (Throwable $e) {
            $this->auditLogger->log(
                courier: 'pathao',
                operation: 'track_shipment',
                httpMethod: 'GET',
                endpoint: $endpoint,
                responseStatus: null,
                requestPayload: ['consignment_id' => $consignmentId],
                responsePayload: ['exception' => $e->getMessage()],
                attempts: max(1, $attempts),
                isError: true,
                errorMessage: $e->getMessage()
            );

            return new ShipmentTrackingResult(
                successful: false,
                courier: 'pathao',
                consignmentId: $consignmentId,
                externalStatus: 'Error',
                internalStatus: 'in_transit',
                rawRequest: ['consignment_id' => $consignmentId],
                rawResponse: ['exception' => $e->getMessage()],
                errorMessage: $e->getMessage()
            );
        }
    }

    public function parseTrackingWebhook(
        array $payload,
        array $headers = [],
        string $rawBody = ''
    ): TrackingWebhookPayload {
        $cfg = config('shipping.couriers.pathao');
        $secret = (string) ($cfg['webhook_secret'] ?? '');

        if ($secret !== '') {
            $providedSig = (string) (
                $headers['x-pathao-signature'][0]
                ?? $headers['x-signature'][0]
                ?? ''
            );

            $expectedHmac = hash_hmac('sha256', $rawBody !== '' ? $rawBody : (string) json_encode($payload), $secret);
            $validFixedSecret = $providedSig !== '' && hash_equals($secret, $providedSig);
            $validHmac = $providedSig !== '' && hash_equals($expectedHmac, $providedSig);

            if (!$validFixedSecret && !$validHmac) {
                throw new CourierWebhookSignatureException('Invalid Pathao webhook signature.');
            }
        }

        $consignmentId = (string) ($payload['consignment_id'] ?? '');
        $merchantOrderId = isset($payload['merchant_order_id']) ? (string) $payload['merchant_order_id'] : null;
        $externalStatus = (string) ($payload['order_status'] ?? $payload['event'] ?? 'Updated');
        $internalStatus = $this->mapExternalStatus($externalStatus);
        $timestamp = (string) ($payload['updated_at'] ?? $payload['timestamp'] ?? '');
        $eventKey = (string) ($payload['event_id'] ?? ('pathao-' . $consignmentId . '-' . strtolower($externalStatus) . '-' . $timestamp));

        $collectedCodPaisa = null;
        if (isset($payload['collected_amount'])) {
            $collectedCodPaisa = (int) round(((float) $payload['collected_amount']) * 100);
        } elseif (isset($payload['amount_to_collect']) && $internalStatus === 'delivered') {
            $collectedCodPaisa = (int) round(((float) $payload['amount_to_collect']) * 100);
        }

        return new TrackingWebhookPayload(
            courier: 'pathao',
            consignmentId: $consignmentId,
            merchantOrderReference: $merchantOrderId,
            eventKey: substr($eventKey, 0, 128),
            externalStatus: $externalStatus,
            internalStatus: $internalStatus,
            description: (string) ($payload['reason'] ?? $payload['note'] ?? "Pathao webhook event: {$externalStatus}"),
            location: isset($payload['hub_name']) ? (string) $payload['hub_name'] : null,
            collectedCodAmountPaisa: $collectedCodPaisa,
            occurredAt: $timestamp !== '' ? $timestamp : now()->toIso8601String(),
            rawPayload: SensitiveDataRedactor::redact($payload)
        );
    }

    public function mapExternalStatus(string $externalStatus): string
    {
        $normalized = strtolower(trim(str_replace(['-', ' ', '.'], '_', $externalStatus)));

        return match ($normalized) {
            'pending',
            'order_created',
            'pickup_requested',
            'order_pickup_requested',
            'assigned_for_pickup' => 'created',

            'picked',
            'picked_up',
            'order_picked' => 'picked_up',

            'at_the_sorting_hub',
            'order_at_the_sorting_hub',
            'in_transit',
            'order_in_transit',
            'received_at_last_mile_hub' => 'in_transit',

            'assigned_for_delivery',
            'out_for_delivery',
            'order_assigned_for_delivery' => 'out_for_delivery',

            'delivered',
            'order_delivered',
            'partial_delivery',
            'order_partial_delivery',
            'paid',
            'order_paid' => 'delivered',

            'delivery_failed',
            'order_delivery_failed',
            'on_hold',
            'order_on_hold' => 'failed',

            'return',
            'order_return',
            'return_in_transit' => 'returning',

            'returned',
            'order_returned',
            'returned_to_merchant' => 'returned',

            'cancelled',
            'canceled',
            'order_cancelled',
            'pickup_cancelled' => 'cancelled',

            default => 'in_transit',
        };
    }

    /**
     * Obtain and cache Pathao OAuth2 Bearer token via `/aladdin/api/v1/issue-token`.
     */
    private function getAccessToken(?int $orderId = null): string
    {
        $cached = Cache::get('shipping_pathao_access_token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $cfg = config('shipping.couriers.pathao');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/aladdin/api/v1/issue-token";

        $payload = [
            'client_id'     => (string) ($cfg['client_id'] ?? ''),
            'client_secret' => (string) ($cfg['client_secret'] ?? ''),
            'username'      => (string) ($cfg['username'] ?? ''),
            'password'      => (string) ($cfg['password'] ?? ''),
            'grant_type'    => 'password',
        ];

        $attempts = 0;
        $response = $this->sendWithRetry(
            method: 'POST',
            url: $endpoint,
            token: null,
            payload: $payload,
            attempts: $attempts
        );

        $body = $response->json() ?? [];
        $token = (string) ($body['access_token'] ?? '');

        $this->auditLogger->log(
            courier: 'pathao',
            operation: 'issue_token',
            httpMethod: 'POST',
            endpoint: $endpoint,
            responseStatus: $response->status(),
            requestPayload: $payload,
            responsePayload: $body,
            attempts: $attempts,
            isError: $token === '',
            errorMessage: $token === '' ? (string) ($body['message'] ?? 'Failed to issue Pathao access token.') : null,
            orderId: $orderId
        );

        if ($token !== '') {
            $ttl = max(60, ((int) ($body['expires_in'] ?? 3600)) - 60);
            Cache::put('shipping_pathao_access_token', $token, $ttl);
        }

        return $token;
    }

    /**
     * Execute HTTP request with retry handling on transient 5xx or connection errors.
     */
    private function sendWithRetry(
        string $method,
        string $url,
        ?string $token,
        array $payload,
        int &$attempts
    ): Response {
        $maxRetries = max(1, (int) config('shipping.http_retry_times', 3));
        $sleepMs = max(0, (int) config('shipping.http_retry_sleep_ms', 100));
        $timeoutSec = max(5, (int) config('shipping.http_timeout_sec', 15));

        $attempts = 0;

        $pendingRequest = Http::timeout($timeoutSec)
            ->acceptJson()
            ->asJson()
            ->retry(
                $maxRetries,
                $sleepMs,
                function (Throwable $exception) use (&$attempts) {
                    $attempts++;
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }
                    if (method_exists($exception, 'getCode') && $exception->getCode() >= 500) {
                        return true;
                    }
                    return false;
                },
                throw: false
            );

        if ($token !== null && $token !== '') {
            $pendingRequest = $pendingRequest->withToken($token);
        }

        $attempts = max(1, $attempts + 1);

        return strtoupper($method) === 'GET'
            ? $pendingRequest->get($url)
            : $pendingRequest->post($url, $payload);
    }

    private function resolveCityId(string $district, string $division): int
    {
        $normDistrict = strtolower(trim($district));
        if (isset(self::CITY_MAP[$normDistrict])) {
            return self::CITY_MAP[$normDistrict];
        }

        $normDivision = strtolower(trim($division));
        return self::CITY_MAP[$normDivision] ?? 1;
    }

    private function normalizeBdPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            return substr($digits, 2);
        }

        return $digits;
    }
}

