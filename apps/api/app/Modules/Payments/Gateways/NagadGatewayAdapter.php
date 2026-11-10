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
use Illuminate\Support\Str;

/**
 * Official Nagad Merchant Checkout API (v-0.2.0) Adapter.
 *
 * Endpoints implemented per official Nagad Merchant Integration Specification:
 * - POST /api/dfs/check-out/initialize/{merchantId}/{orderId}
 * - POST /api/dfs/check-out/complete/{paymentReferenceId}
 * - GET  /api/dfs/verify/payment/{paymentReferenceId}
 * - POST /api/dfs/purchase/cancel
 */
class NagadGatewayAdapter implements PaymentGateway
{
    public function __construct(
        protected GatewayAuditLogger $auditLogger
    ) {}

    public function gatewayName(): string
    {
        return 'nagad';
    }

    public function initiate(PaymentInitiationRequest $request): PaymentInitiationResult
    {
        $cfg = config('payment.gateways.nagad');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $merchantId = (string) ($cfg['merchant_id'] ?? '');
        $datetime = now()->format('YmdHis');
        $challenge = Str::random(40);

        $initEndpoint = "{$baseUrl}/api/dfs/check-out/initialize/{$merchantId}/{$request->merchantReference}";
        $sensitiveInit = [
            'merchantId' => $merchantId,
            'datetime'   => $datetime,
            'orderId'    => $request->merchantReference,
            'challenge'  => $challenge,
        ];

        $initPayload = [
            'accountNumber' => (string) ($cfg['merchant_number'] ?? ''),
            'dateTime'      => $datetime,
            'sensitiveData' => base64_encode(json_encode($sensitiveInit)),
            'signature'     => hash_hmac('sha256', json_encode($sensitiveInit), (string) ($cfg['private_key'] ?? 'nagad')),
        ];

        $headers = [
            'X-KM-Api-Version' => 'v-0.2.0',
            'X-KM-IP-V4'       => '127.0.0.1',
            'X-KM-Client-Type' => 'PC_WEB',
            'Content-Type'     => 'application/json',
        ];

        $initRes = Http::withHeaders($headers)->timeout(15)->post($initEndpoint, $initPayload);
        $initBody = $initRes->json() ?? [];

        $this->auditLogger->log(
            gateway: 'nagad',
            operation: 'initialize',
            httpMethod: 'POST',
            endpoint: $initEndpoint,
            responseStatus: $initRes->status(),
            requestPayload: ['headers' => $headers, 'body' => $initPayload],
            responsePayload: $initBody,
            orderId: $request->orderId
        );

        $paymentReferenceId = (string) ($initBody['paymentReferenceId'] ?? '');
        if ($paymentReferenceId === '') {
            return new PaymentInitiationResult(
                successful: false,
                gateway: 'nagad',
                merchantReference: $request->merchantReference,
                status: 'failed',
                rawRequest: SensitiveDataRedactor::redact($initPayload),
                rawResponse: SensitiveDataRedactor::redact($initBody),
                errorMessage: (string) ($initBody['message'] ?? 'Nagad initialization failed.')
            );
        }

        $completeEndpoint = "{$baseUrl}/api/dfs/check-out/complete/{$paymentReferenceId}";
        $sensitiveComplete = [
            'merchantId'   => $merchantId,
            'orderId'      => $request->merchantReference,
            'currencyCode' => '050', // ISO 4217 numeric code for BDT
            'amount'       => $request->amountDecimalString(),
            'challenge'    => (string) ($initBody['challenge'] ?? $challenge),
        ];

        $completePayload = [
            'sensitiveData'       => base64_encode(json_encode($sensitiveComplete)),
            'signature'           => hash_hmac('sha256', json_encode($sensitiveComplete), (string) ($cfg['private_key'] ?? 'nagad')),
            'merchantCallbackURL' => $request->callbackUrl ?? $cfg['callback_url'],
        ];

        $completeRes = Http::withHeaders($headers)->timeout(15)->post($completeEndpoint, $completePayload);
        $completeBody = $completeRes->json() ?? [];

        $this->auditLogger->log(
            gateway: 'nagad',
            operation: 'complete_init',
            httpMethod: 'POST',
            endpoint: $completeEndpoint,
            responseStatus: $completeRes->status(),
            requestPayload: ['headers' => $headers, 'body' => $completePayload],
            responsePayload: $completeBody,
            orderId: $request->orderId
        );

        $callBackUrl = isset($completeBody['callBackUrl']) ? (string) $completeBody['callBackUrl'] : null;
        $isSuccess = $completeRes->successful() && $callBackUrl !== null;

        return new PaymentInitiationResult(
            successful: $isSuccess,
            gateway: 'nagad',
            merchantReference: $request->merchantReference,
            gatewayPaymentId: $paymentReferenceId,
            redirectUrl: $callBackUrl,
            status: $isSuccess ? 'pending' : 'failed',
            rawRequest: SensitiveDataRedactor::redact($completePayload),
            rawResponse: SensitiveDataRedactor::redact($completeBody),
            errorMessage: $isSuccess ? null : (string) ($completeBody['message'] ?? 'Nagad checkout completion failed.')
        );
    }

    public function verify(PaymentVerificationRequest $request): PaymentVerificationResult
    {
        $cfg = config('payment.gateways.nagad');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $paymentRefId = (string) ($request->gatewayPaymentId
            ?? $request->callbackPayload['payment_ref_id']
            ?? $request->callbackPayload['paymentReferenceId']
            ?? '');

        $endpoint = "{$baseUrl}/api/dfs/verify/payment/{$paymentRefId}";
        $response = Http::timeout(15)->get($endpoint);
        $body = $response->json() ?? [];

        $this->auditLogger->log(
            gateway: 'nagad',
            operation: 'verify',
            httpMethod: 'GET',
            endpoint: $endpoint,
            responseStatus: $response->status(),
            requestPayload: ['payment_reference_id' => $paymentRefId],
            responsePayload: $body
        );

        $status = (string) ($body['status'] ?? '');
        $statusCode = (string) ($body['statusCode'] ?? '');
        $isVerified = strcasecmp($status, 'Success') === 0 && ($statusCode === '' || $statusCode === '000');

        $amountDecimal = (float) ($body['amount'] ?? 0);
        $amountPaisa = (int) round($amountDecimal * 100);
        $merchantOrderId = (string) ($body['orderId'] ?? $request->merchantReference);
        $issuerRef = isset($body['issuerPaymentRefNo']) ? (string) $body['issuerPaymentRefNo'] : null;

        return new PaymentVerificationResult(
            verified: $isVerified,
            gateway: 'nagad',
            status: $isVerified ? 'paid' : 'failed',
            merchantReference: $merchantOrderId,
            gatewayPaymentId: $paymentRefId !== '' ? $paymentRefId : null,
            gatewayTransactionId: $issuerRef,
            amountPaisa: $amountPaisa,
            currency: 'BDT',
            merchantId: isset($body['merchantId']) ? (string) $body['merchantId'] : null,
            rawRequest: ['payment_reference_id' => $paymentRefId],
            rawResponse: SensitiveDataRedactor::redact($body),
            failureReason: $isVerified ? null : (string) ($body['message'] ?? 'Nagad verification failed.')
        );
    }

    public function refund(PaymentRefundRequest $request): PaymentRefundResult
    {
        $cfg = config('payment.gateways.nagad');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $endpoint = "{$baseUrl}/api/dfs/purchase/cancel";

        $payload = [
            'paymentReferenceId' => $request->gatewayPaymentId,
            'orderId'            => $request->merchantReference,
            'cancelAmount'       => $request->refundAmountDecimalString(),
            'reason'             => $request->reason,
        ];

        $response = Http::timeout(15)->post($endpoint, $payload);
        $body = $response->json() ?? [];

        $this->auditLogger->log(
            gateway: 'nagad',
            operation: 'refund',
            httpMethod: 'POST',
            endpoint: $endpoint,
            responseStatus: $response->status(),
            requestPayload: $payload,
            responsePayload: $body,
            orderId: $request->orderId
        );

        $isSuccess = $response->successful() && strcasecmp((string) ($body['status'] ?? ''), 'Success') === 0;

        return new PaymentRefundResult(
            successful: $isSuccess,
            gateway: 'nagad',
            gatewayRefundId: isset($body['cancelTrxId']) ? (string) $body['cancelTrxId'] : null,
            refundedAmountPaisa: $request->refundAmountPaisa,
            currency: $request->currency,
            status: $isSuccess ? 'completed' : 'failed',
            rawRequest: SensitiveDataRedactor::redact($payload),
            rawResponse: SensitiveDataRedactor::redact($body),
            errorMessage: $isSuccess ? null : (string) ($body['message'] ?? 'Nagad refund failed.')
        );
    }

    public function parseWebhook(array $payload, array $headers = [], string $rawBody = ''): WebhookNotificationPayload
    {
        $cfg = config('payment.gateways.nagad');
        $secret = (string) ($cfg['webhook_secret'] ?? '');

        if ($secret !== '') {
            $providedSig = (string) ($headers['x-nagad-signature'][0] ?? $headers['x-signature'][0] ?? '');
            $expectedSig = hash_hmac('sha256', $rawBody !== '' ? $rawBody : json_encode($payload), $secret);
            if ($providedSig === '' || !hash_equals($expectedSig, $providedSig)) {
                throw new WebhookSignatureException('Invalid Nagad webhook signature.');
            }
        }

        $paymentRef = (string) ($payload['payment_ref_id'] ?? $payload['paymentReferenceId'] ?? '');
        $eventId = (string) ($payload['event_id'] ?? ('nagad-' . $paymentRef));

        return new WebhookNotificationPayload(
            gateway: 'nagad',
            gatewayEventId: $eventId,
            merchantReference: (string) ($payload['order_id'] ?? $payload['orderId'] ?? $payload['merchant_reference'] ?? ''),
            gatewayPaymentId: $paymentRef !== '' ? $paymentRef : null,
            gatewayTransactionId: isset($payload['issuer_payment_ref']) ? (string) $payload['issuer_payment_ref'] : null,
            eventType: (string) ($payload['status'] ?? 'nagad.callback'),
            rawPayload: SensitiveDataRedactor::redact($payload)
        );
    }
}

