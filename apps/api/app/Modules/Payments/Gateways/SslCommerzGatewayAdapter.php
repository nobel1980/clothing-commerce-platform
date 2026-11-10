<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Payments\Services\GatewayAuditLogger;
use App\Shared\Contracts\Payments\PaymentGateway;
use App\Shared\Contracts\Payments\PaymentInitiationRequest;
use App\Shared\Contracts\Payments\PaymentInitiationResult;
use App\Shared\Contracts\Payments\PaymentRefundRequest;
use App\Shared\Contracts\Payments\PaymentRefundResult;
use App\Shared\Contracts\Payments\PaymentVerificationRequest;
use App\Shared\Contracts\Payments\PaymentVerificationResult;
use App\Shared\Contracts\Payments\WebhookNotificationPayload;
use App\Shared\Exceptions\Payments\WebhookSignatureException;
use App\Shared\Support\SensitiveDataRedactor;
use Illuminate\Support\Facades\Http;

/**
 * Official SSLCommerz Payment Gateway API (v4) Adapter.
 *
 * Endpoints implemented per official SSLCommerz Merchant Integration Documentation:
 * - POST /gwprocess/v4/api.php
 * - GET  /validator/api/validationserverAPI.php
 * - GET  /validator/api/merchantTransIDvalidationAPI.php (Refund API)
 */
class SslCommerzGatewayAdapter implements PaymentGateway
{
    public function __construct(
        protected GatewayAuditLogger $auditLogger
    ) {}

    public function gatewayName(): string
    {
        return 'sslcommerz';
    }

    public function initiate(PaymentInitiationRequest $request): PaymentInitiationResult
    {
        $cfg = config('payment.gateways.sslcommerz');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/gwprocess/v4/api.php";

        $payload = [
            'store_id'         => (string) ($cfg['store_id'] ?? ''),
            'store_passwd'     => (string) ($cfg['store_password'] ?? ''),
            'total_amount'     => $request->amountDecimalString(),
            'currency'         => $request->currency,
            'tran_id'          => $request->merchantReference,
            'success_url'      => $request->callbackUrl ?? $cfg['success_url'],
            'fail_url'         => $cfg['fail_url'],
            'cancel_url'       => $cfg['cancel_url'],
            'ipn_url'          => $cfg['ipn_url'],
            'cus_name'         => $request->customerName,
            'cus_email'        => $request->customerEmail ?? 'customer@clothing-commerce.com',
            'cus_add1'         => $request->customerAddress,
            'cus_city'         => $request->customerCity,
            'cus_postcode'     => $request->customerPostcode,
            'cus_country'      => 'Bangladesh',
            'cus_phone'        => $request->customerPhone,
            'shipping_method'  => 'YES',
            'num_of_item'      => 1,
            'product_name'     => "Order {$request->orderNumber}",
            'product_category' => 'Apparel',
            'product_profile'  => 'general',
        ];

        $response = Http::asForm()->timeout(15)->post($endpoint, $payload);
        $body = $response->json() ?? [];

        $this->auditLogger->log(
            gateway: 'sslcommerz',
            operation: 'initiate',
            httpMethod: 'POST',
            endpoint: $endpoint,
            responseStatus: $response->status(),
            requestPayload: $payload,
            responsePayload: $body,
            orderId: $request->orderId
        );

        $status = strtoupper((string) ($body['status'] ?? ''));
        $sessionKey = isset($body['sessionkey']) ? (string) $body['sessionkey'] : null;
        $gatewayUrl = isset($body['GatewayPageURL']) && $body['GatewayPageURL'] !== ''
            ? (string) $body['GatewayPageURL']
            : null;
        $isSuccess = $response->successful() && $status === 'SUCCESS' && $gatewayUrl !== null;

        return new PaymentInitiationResult(
            successful: $isSuccess,
            gateway: 'sslcommerz',
            merchantReference: $request->merchantReference,
            gatewayPaymentId: $sessionKey,
            redirectUrl: $gatewayUrl,
            status: $isSuccess ? 'pending' : 'failed',
            rawRequest: SensitiveDataRedactor::redact($payload),
            rawResponse: SensitiveDataRedactor::redact($body),
            errorMessage: $isSuccess ? null : (string) ($body['failedreason'] ?? 'SSLCommerz session initiation failed.')
        );
    }

    public function verify(PaymentVerificationRequest $request): PaymentVerificationResult
    {
        $cfg = config('payment.gateways.sslcommerz');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/validator/api/validationserverAPI.php";

        $valId = (string) ($request->callbackPayload['val_id']
            ?? $request->gatewayTransactionId
            ?? $request->gatewayPaymentId
            ?? '');

        $queryParams = [
            'val_id'       => $valId,
            'store_id'     => (string) ($cfg['store_id'] ?? ''),
            'store_passwd' => (string) ($cfg['store_password'] ?? ''),
            'v'            => 1,
            'format'       => 'json',
        ];

        $response = Http::timeout(15)->get($endpoint, $queryParams);
        $body = $response->json() ?? [];

        $this->auditLogger->log(
            gateway: 'sslcommerz',
            operation: 'verify',
            httpMethod: 'GET',
            endpoint: $endpoint,
            responseStatus: $response->status(),
            requestPayload: $queryParams,
            responsePayload: $body
        );

        $status = strtoupper((string) ($body['status'] ?? ''));
        $isVerified = in_array($status, ['VALID', 'VALIDATED'], true);
        $amountDecimal = (float) ($body['currency_amount'] ?? $body['amount'] ?? 0);
        $amountPaisa = (int) round($amountDecimal * 100);
        $currency = (string) ($body['currency_type'] ?? $body['currency'] ?? 'BDT');
        $tranId = (string) ($body['tran_id'] ?? $request->merchantReference);
        $bankTranId = isset($body['bank_tran_id']) ? (string) $body['bank_tran_id'] : $valId;

        return new PaymentVerificationResult(
            verified: $isVerified,
            gateway: 'sslcommerz',
            status: $isVerified ? 'paid' : 'failed',
            merchantReference: $tranId,
            gatewayPaymentId: $valId !== '' ? $valId : null,
            gatewayTransactionId: $bankTranId !== '' ? $bankTranId : null,
            amountPaisa: $amountPaisa,
            currency: $currency,
            merchantId: isset($body['store_id']) ? (string) $body['store_id'] : null,
            rawRequest: SensitiveDataRedactor::redact($queryParams),
            rawResponse: SensitiveDataRedactor::redact($body),
            failureReason: $isVerified ? null : (string) ($body['error'] ?? 'SSLCommerz validation failed.')
        );
    }

    public function refund(PaymentRefundRequest $request): PaymentRefundResult
    {
        $cfg = config('payment.gateways.sslcommerz');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/validator/api/merchantTransIDvalidationAPI.php";

        $queryParams = [
            'bank_tran_id'   => $request->gatewayTransactionId,
            'refund_amount'  => $request->refundAmountDecimalString(),
            'refund_remarks' => $request->reason,
            'store_id'       => (string) ($cfg['store_id'] ?? ''),
            'store_passwd'   => (string) ($cfg['store_password'] ?? ''),
            'v'              => 1,
            'format'         => 'json',
        ];

        $response = Http::timeout(15)->get($endpoint, $queryParams);
        $body = $response->json() ?? [];

        $this->auditLogger->log(
            gateway: 'sslcommerz',
            operation: 'refund',
            httpMethod: 'GET',
            endpoint: $endpoint,
            responseStatus: $response->status(),
            requestPayload: $queryParams,
            responsePayload: $body,
            orderId: $request->orderId
        );

        $status = strtolower((string) ($body['status'] ?? ''));
        $isSuccess = in_array($status, ['success', 'processing'], true);

        return new PaymentRefundResult(
            successful: $isSuccess,
            gateway: 'sslcommerz',
            gatewayRefundId: isset($body['refund_ref_id']) ? (string) $body['refund_ref_id'] : null,
            refundedAmountPaisa: $request->refundAmountPaisa,
            currency: $request->currency,
            status: $isSuccess ? 'completed' : 'failed',
            rawRequest: SensitiveDataRedactor::redact($queryParams),
            rawResponse: SensitiveDataRedactor::redact($body),
            errorMessage: $isSuccess ? null : (string) ($body['errorReason'] ?? 'SSLCommerz refund failed.')
        );
    }

    public function parseWebhook(array $payload, array $headers = [], string $rawBody = ''): WebhookNotificationPayload
    {
        $cfg = config('payment.gateways.sslcommerz');
        $secret = (string) ($cfg['webhook_secret'] ?? '');

        if ($secret !== '') {
            $providedSig = (string) ($headers['x-sslcommerz-signature'][0] ?? $headers['x-signature'][0] ?? '');
            $expectedSig = hash_hmac('sha256', $rawBody !== '' ? $rawBody : json_encode($payload), $secret);
            if ($providedSig === '' || !hash_equals($expectedSig, $providedSig)) {
                throw new WebhookSignatureException('Invalid SSLCommerz IPN signature.');
            }
        }

        $valId = (string) ($payload['val_id'] ?? '');
        $tranId = (string) ($payload['tran_id'] ?? $payload['merchant_reference'] ?? '');
        $bankTranId = (string) ($payload['bank_tran_id'] ?? $valId);
        $eventId = (string) ($payload['event_id'] ?? ('sslc-' . ($valId !== '' ? $valId : $tranId)));

        return new WebhookNotificationPayload(
            gateway: 'sslcommerz',
            gatewayEventId: $eventId,
            merchantReference: $tranId,
            gatewayPaymentId: $valId !== '' ? $valId : null,
            gatewayTransactionId: $bankTranId !== '' ? $bankTranId : null,
            eventType: (string) ($payload['status'] ?? 'sslcommerz.ipn'),
            rawPayload: SensitiveDataRedactor::redact($payload)
        );
    }
}

