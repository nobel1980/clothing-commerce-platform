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
use App\Shared\Exceptions\Payments\PaymentConfigurationException;
use App\Shared\Exceptions\Payments\WebhookSignatureException;
use App\Shared\Support\SensitiveDataRedactor;

/**
 * Deterministic Mock Payment Gateway for Automated Testing ONLY.
 *
 * CRITICAL GUARDRAIL: Refuses to execute in production (`APP_ENV=production`
 * or `payment.allow_mock_gateway === false`).
 */
class MockPaymentGatewayAdapter implements PaymentGateway
{
    public function __construct(
        protected GatewayAuditLogger $auditLogger
    ) {
        $this->assertNotProduction();
    }

    public function gatewayName(): string
    {
        $this->assertNotProduction();
        return 'mock';
    }

    public function initiate(PaymentInitiationRequest $request): PaymentInitiationResult
    {
        $this->assertNotProduction();

        $paymentId = 'MOCK-PAY-' . $request->merchantReference;
        $redirectUrl = 'https://mock-gateway.test/pay/' . $paymentId;

        $reqPayload = [
            'merchant_reference' => $request->merchantReference,
            'amount_paisa'       => $request->amountPaisa,
            'currency'           => $request->currency,
            'app_secret'         => 'mock-secret-must-be-redacted',
        ];
        $resPayload = [
            'payment_id'   => $paymentId,
            'redirect_url' => $redirectUrl,
            'id_token'     => 'mock-token-must-be-redacted',
        ];

        $this->auditLogger->log(
            gateway: 'mock',
            operation: 'initiate',
            httpMethod: 'POST',
            endpoint: 'https://mock-gateway.test/api/initiate',
            responseStatus: 200,
            requestPayload: $reqPayload,
            responsePayload: $resPayload,
            orderId: $request->orderId
        );

        return new PaymentInitiationResult(
            successful: true,
            gateway: 'mock',
            merchantReference: $request->merchantReference,
            gatewayPaymentId: $paymentId,
            redirectUrl: $redirectUrl,
            status: 'pending',
            rawRequest: SensitiveDataRedactor::redact($reqPayload),
            rawResponse: SensitiveDataRedactor::redact($resPayload)
        );
    }

    public function verify(PaymentVerificationRequest $request): PaymentVerificationResult
    {
        $this->assertNotProduction();

        $cb = $request->callbackPayload;
        $simulatedStatus = (string) ($cb['simulated_status'] ?? 'paid');
        $isPaid = $simulatedStatus === 'paid';

        $amountPaisa = isset($cb['simulated_amount_paisa'])
            ? (int) $cb['simulated_amount_paisa']
            : (int) ($cb['amount_paisa'] ?? 0);

        $currency = (string) ($cb['simulated_currency'] ?? $cb['currency'] ?? 'BDT');
        $merchantRef = (string) ($cb['simulated_merchant_reference'] ?? $request->merchantReference);
        $merchantId = (string) ($cb['simulated_merchant_id'] ?? config('payment.gateways.mock.merchant_id', 'MERCHANT_BD_TEST'));
        $trxId = (string) ($request->gatewayTransactionId ?? $cb['trx_id'] ?? ('MOCK-TRX-' . $request->merchantReference));

        $reqPayload = [
            'merchant_reference' => $request->merchantReference,
            'gateway_payment_id' => $request->gatewayPaymentId,
            'private_key'        => 'mock-private-key-must-be-redacted',
        ];
        $resPayload = [
            'status'             => $simulatedStatus,
            'merchant_reference' => $merchantRef,
            'merchant_id'        => $merchantId,
            'trx_id'             => $trxId,
            'amount_paisa'       => $amountPaisa,
            'currency'           => $currency,
        ];

        $this->auditLogger->log(
            gateway: 'mock',
            operation: 'verify',
            httpMethod: 'POST',
            endpoint: 'https://mock-gateway.test/api/verify',
            responseStatus: 200,
            requestPayload: $reqPayload,
            responsePayload: $resPayload
        );

        return new PaymentVerificationResult(
            verified: $isPaid,
            gateway: 'mock',
            status: $isPaid ? 'paid' : 'failed',
            merchantReference: $merchantRef,
            gatewayPaymentId: $request->gatewayPaymentId ?? ('MOCK-PAY-' . $request->merchantReference),
            gatewayTransactionId: $trxId,
            amountPaisa: $amountPaisa,
            currency: $currency,
            merchantId: $merchantId,
            rawRequest: SensitiveDataRedactor::redact($reqPayload),
            rawResponse: SensitiveDataRedactor::redact($resPayload),
            failureReason: $isPaid ? null : (string) ($cb['failure_reason'] ?? 'Mock payment declined.')
        );
    }

    public function refund(PaymentRefundRequest $request): PaymentRefundResult
    {
        $this->assertNotProduction();

        $refundId = 'MOCK-REF-' . $request->gatewayTransactionId;
        $reqPayload = [
            'gateway_transaction_id' => $request->gatewayTransactionId,
            'refund_amount_paisa'    => $request->refundAmountPaisa,
            'reason'                 => $request->reason,
            'store_passwd'           => 'secret-passwd',
        ];
        $resPayload = [
            'refund_id' => $refundId,
            'status'    => 'completed',
        ];

        $this->auditLogger->log(
            gateway: 'mock',
            operation: 'refund',
            httpMethod: 'POST',
            endpoint: 'https://mock-gateway.test/api/refund',
            responseStatus: 200,
            requestPayload: $reqPayload,
            responsePayload: $resPayload,
            orderId: $request->orderId
        );

        return new PaymentRefundResult(
            successful: true,
            gateway: 'mock',
            gatewayRefundId: $refundId,
            refundedAmountPaisa: $request->refundAmountPaisa,
            currency: $request->currency,
            status: 'completed',
            rawRequest: SensitiveDataRedactor::redact($reqPayload),
            rawResponse: SensitiveDataRedactor::redact($resPayload)
        );
    }

    public function parseWebhook(array $payload, array $headers = [], string $rawBody = ''): WebhookNotificationPayload
    {
        $this->assertNotProduction();

        $secret = (string) config('payment.gateways.mock.webhook_secret', '');
        if ($secret !== '') {
            $providedSig = (string) ($headers['x-mock-signature'][0] ?? '');
            $expectedSig = hash_hmac('sha256', $rawBody !== '' ? $rawBody : json_encode($payload), $secret);
            if ($providedSig === '' || !hash_equals($expectedSig, $providedSig)) {
                throw new WebhookSignatureException('Invalid mock webhook signature.');
            }
        }

        return new WebhookNotificationPayload(
            gateway: 'mock',
            gatewayEventId: (string) ($payload['event_id'] ?? ('mock-evt-' . ($payload['merchant_reference'] ?? uniqid()))),
            merchantReference: (string) ($payload['merchant_reference'] ?? ''),
            gatewayPaymentId: isset($payload['payment_id']) ? (string) $payload['payment_id'] : null,
            gatewayTransactionId: isset($payload['trx_id']) ? (string) $payload['trx_id'] : null,
            eventType: (string) ($payload['event_type'] ?? 'payment.completed'),
            rawPayload: SensitiveDataRedactor::redact($payload)
        );
    }

    public function assertNotProduction(): void
    {
        if (app()->environment('production') || !config('payment.allow_mock_gateway', true)) {
            throw new PaymentConfigurationException(
                'Mock payment gateway is strictly forbidden in the production environment.'
            );
        }
    }
}

