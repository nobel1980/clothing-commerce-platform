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
 * Official bKash Tokenized Checkout API (v1.2.0-beta) Adapter.
 *
 * Endpoints implemented per official bKash Merchant Documentation:
 * - POST /tokenized/checkout/token/grant
 * - POST /tokenized/checkout/create
 * - POST /tokenized/checkout/execute
 * - POST /tokenized/checkout/payment/status
 * - POST /tokenized/checkout/payment/refund
 */
class BkashGatewayAdapter implements PaymentGateway
{
    public function __construct(
        protected GatewayAuditLogger $auditLogger
    ) {}

    public function gatewayName(): string
    {
        return 'bkash';
    }

    public function initiate(PaymentInitiationRequest $request): PaymentInitiationResult
    {
        $cfg = config('payment.gateways.bkash');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $idToken = $this->grantToken($request->orderId);

        $endpoint = "{$baseUrl}/tokenized/checkout/create";
        $payload = [
            'mode'                  => '0011',
            'payerReference'        => $request->customerPhone,
            'callbackURL'           => $request->callbackUrl ?? $cfg['callback_url'],
            'amount'                => $request->amountDecimalString(),
            'currency'              => 'BDT',
            'intent'                => 'sale',
            'merchantInvoiceNumber' => $request->merchantReference,
        ];

        $headers = [
            'Authorization' => $idToken,
            'X-APP-Key'     => (string) ($cfg['app_key'] ?? ''),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];

        $response = Http::withHeaders($headers)->timeout(15)->post($endpoint, $payload);
        $body = $response->json() ?? [];

        $this->auditLogger->log(
            gateway: 'bkash',
            operation: 'initiate',
            httpMethod: 'POST',
            endpoint: $endpoint,
            responseStatus: $response->status(),
            requestPayload: ['headers' => $headers, 'body' => $payload],
            responsePayload: $body,
            orderId: $request->orderId
        );

        $statusCode = (string) ($body['statusCode'] ?? '');
        $paymentId = isset($body['paymentID']) ? (string) $body['paymentID'] : null;
        $bkashUrl = isset($body['bkashURL']) ? (string) $body['bkashURL'] : null;
        $isSuccess = $response->successful() && $statusCode === '0000' && $paymentId !== null;

        return new PaymentInitiationResult(
            successful: $isSuccess,
            gateway: 'bkash',
            merchantReference: $request->merchantReference,
            gatewayPaymentId: $paymentId,
            redirectUrl: $bkashUrl,
            status: $isSuccess ? 'pending' : 'failed',
            rawRequest: SensitiveDataRedactor::redact(['headers' => $headers, 'body' => $payload]),
            rawResponse: SensitiveDataRedactor::redact($body),
            errorMessage: $isSuccess ? null : (string) ($body['statusMessage'] ?? 'bKash payment creation failed.')
        );
    }

    public function verify(PaymentVerificationRequest $request): PaymentVerificationResult
    {
        $cfg = config('payment.gateways.bkash');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $idToken = $this->grantToken();

        $paymentId = (string) ($request->gatewayPaymentId ?? $request->callbackPayload['paymentID'] ?? '');
        $endpoint = "{$baseUrl}/tokenized/checkout/execute";
        $payload = ['paymentID' => $paymentId];
        $headers = [
            'Authorization' => $idToken,
            'X-APP-Key'     => (string) ($cfg['app_key'] ?? ''),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];

        $response = Http::withHeaders($headers)->timeout(15)->post($endpoint, $payload);
        $body = $response->json() ?? [];

        $this->auditLogger->log(
            gateway: 'bkash',
            operation: 'verify_execute',
            httpMethod: 'POST',
            endpoint: $endpoint,
            responseStatus: $response->status(),
            requestPayload: ['headers' => $headers, 'body' => $payload],
            responsePayload: $body
        );

        // If already executed (e.g. statusCode 2062), query payment status per official bKash spec
        if (($body['statusCode'] ?? '') === '2062' || empty($body['transactionStatus'])) {
            $statusEndpoint = "{$baseUrl}/tokenized/checkout/payment/status";
            $statusRes = Http::withHeaders($headers)->timeout(15)->post($statusEndpoint, $payload);
            $body = $statusRes->json() ?? $body;

            $this->auditLogger->log(
                gateway: 'bkash',
                operation: 'verify_status',
                httpMethod: 'POST',
                endpoint: $statusEndpoint,
                responseStatus: $statusRes->status(),
                requestPayload: ['headers' => $headers, 'body' => $payload],
                responsePayload: $body
            );
        }

        $txStatus = (string) ($body['transactionStatus'] ?? '');
        $statusCode = (string) ($body['statusCode'] ?? '');
        $isCompleted = $statusCode === '0000' && strcasecmp($txStatus, 'Completed') === 0;
        $amountDecimal = (float) ($body['amount'] ?? 0);
        $amountPaisa = (int) round($amountDecimal * 100);
        $currency = (string) ($body['currency'] ?? 'BDT');
        $merchantInvoice = (string) ($body['merchantInvoiceNumber'] ?? $request->merchantReference);
        $trxId = isset($body['trxID']) ? (string) $body['trxID'] : null;

        return new PaymentVerificationResult(
            verified: $isCompleted,
            gateway: 'bkash',
            status: $isCompleted ? 'paid' : 'failed',
            merchantReference: $merchantInvoice,
            gatewayPaymentId: $paymentId !== '' ? $paymentId : null,
            gatewayTransactionId: $trxId,
            amountPaisa: $amountPaisa,
            currency: $currency,
            merchantId: null,
            rawRequest: SensitiveDataRedactor::redact(['headers' => $headers, 'body' => $payload]),
            rawResponse: SensitiveDataRedactor::redact($body),
            failureReason: $isCompleted ? null : (string) ($body['statusMessage'] ?? 'bKash execution not completed.')
        );
    }

    public function refund(PaymentRefundRequest $request): PaymentRefundResult
    {
        $cfg = config('payment.gateways.bkash');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $idToken = $this->grantToken($request->orderId);

        $endpoint = "{$baseUrl}/tokenized/checkout/payment/refund";
        $payload = [
            'paymentID' => $request->gatewayPaymentId,
            'trxID'     => $request->gatewayTransactionId,
            'amount'    => $request->refundAmountDecimalString(),
            'sku'       => $request->sku,
            'reason'    => $request->reason,
        ];
        $headers = [
            'Authorization' => $idToken,
            'X-APP-Key'     => (string) ($cfg['app_key'] ?? ''),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];

        $response = Http::withHeaders($headers)->timeout(15)->post($endpoint, $payload);
        $body = $response->json() ?? [];

        $this->auditLogger->log(
            gateway: 'bkash',
            operation: 'refund',
            httpMethod: 'POST',
            endpoint: $endpoint,
            responseStatus: $response->status(),
            requestPayload: ['headers' => $headers, 'body' => $payload],
            responsePayload: $body,
            orderId: $request->orderId
        );

        $isSuccess = ($body['statusCode'] ?? '') === '0000'
            && strcasecmp((string) ($body['transactionStatus'] ?? ''), 'Completed') === 0;

        return new PaymentRefundResult(
            successful: $isSuccess,
            gateway: 'bkash',
            gatewayRefundId: isset($body['refundTrxID']) ? (string) $body['refundTrxID'] : null,
            refundedAmountPaisa: $request->refundAmountPaisa,
            currency: $request->currency,
            status: $isSuccess ? 'completed' : 'failed',
            rawRequest: SensitiveDataRedactor::redact(['headers' => $headers, 'body' => $payload]),
            rawResponse: SensitiveDataRedactor::redact($body),
            errorMessage: $isSuccess ? null : (string) ($body['statusMessage'] ?? 'bKash refund failed.')
        );
    }

    public function parseWebhook(array $payload, array $headers = [], string $rawBody = ''): WebhookNotificationPayload
    {
        $cfg = config('payment.gateways.bkash');
        $secret = (string) ($cfg['webhook_secret'] ?? '');

        if ($secret !== '') {
            $providedSig = (string) ($headers['x-bkash-signature'][0] ?? $headers['x-signature'][0] ?? '');
            $expectedSig = hash_hmac('sha256', $rawBody !== '' ? $rawBody : json_encode($payload), $secret);
            if ($providedSig === '' || !hash_equals($expectedSig, $providedSig)) {
                throw new WebhookSignatureException('Invalid bKash webhook signature.');
            }
        }

        $trxId = (string) ($payload['trxID'] ?? $payload['paymentID'] ?? '');
        $eventId = (string) ($payload['MessageId'] ?? $payload['event_id'] ?? ('bkash-' . $trxId));

        return new WebhookNotificationPayload(
            gateway: 'bkash',
            gatewayEventId: $eventId,
            merchantReference: (string) ($payload['merchantInvoiceNumber'] ?? $payload['merchant_reference'] ?? ''),
            gatewayPaymentId: isset($payload['paymentID']) ? (string) $payload['paymentID'] : null,
            gatewayTransactionId: isset($payload['trxID']) ? (string) $payload['trxID'] : null,
            eventType: (string) ($payload['transactionStatus'] ?? 'bkash.ipn'),
            rawPayload: SensitiveDataRedactor::redact($payload)
        );
    }

    private function grantToken(?int $orderId = null): string
    {
        $cfg = config('payment.gateways.bkash');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/tokenized/checkout/token/grant";

        $headers = [
            'username'     => (string) ($cfg['username'] ?? ''),
            'password'     => (string) ($cfg['password'] ?? ''),
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];
        $payload = [
            'app_key'    => (string) ($cfg['app_key'] ?? ''),
            'app_secret' => (string) ($cfg['app_secret'] ?? ''),
        ];

        $response = Http::withHeaders($headers)->timeout(15)->post($endpoint, $payload);
        $body = $response->json() ?? [];

        $this->auditLogger->log(
            gateway: 'bkash',
            operation: 'grant_token',
            httpMethod: 'POST',
            endpoint: $endpoint,
            responseStatus: $response->status(),
            requestPayload: ['headers' => $headers, 'body' => $payload],
            responsePayload: $body,
            orderId: $orderId
        );

        return (string) ($body['id_token'] ?? '');
    }
}

