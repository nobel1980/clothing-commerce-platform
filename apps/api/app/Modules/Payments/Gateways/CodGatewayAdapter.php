<?php

namespace App\Modules\Payments\Gateways;

use App\Shared\Contracts\Payments\PaymentGateway;
use App\Shared\Contracts\Payments\PaymentInitiationRequest;
use App\Shared\Contracts\Payments\PaymentInitiationResult;
use App\Shared\Contracts\Payments\PaymentRefundRequest;
use App\Shared\Contracts\Payments\PaymentRefundResult;
use App\Shared\Contracts\Payments\PaymentVerificationRequest;
use App\Shared\Contracts\Payments\PaymentVerificationResult;
use App\Shared\Contracts\Payments\WebhookNotificationPayload;

class CodGatewayAdapter implements PaymentGateway
{
    public function gatewayName(): string
    {
        return 'cod';
    }

    public function initiate(PaymentInitiationRequest $request): PaymentInitiationResult
    {
        $codPaymentId = 'COD-' . $request->merchantReference;

        return new PaymentInitiationResult(
            successful: true,
            gateway: 'cod',
            merchantReference: $request->merchantReference,
            gatewayPaymentId: $codPaymentId,
            redirectUrl: null,
            status: 'cod_pending',
            rawRequest: [
                'merchant_reference' => $request->merchantReference,
                'amount_paisa'       => $request->amountPaisa,
                'currency'           => $request->currency,
            ],
            rawResponse: [
                'payment_id' => $codPaymentId,
                'status'     => 'cod_pending',
            ]
        );
    }

    public function verify(PaymentVerificationRequest $request): PaymentVerificationResult
    {
        $amountPaisa = (int) ($request->callbackPayload['amount_paisa'] ?? 0);

        return new PaymentVerificationResult(
            verified: true,
            gateway: 'cod',
            status: 'paid',
            merchantReference: $request->merchantReference,
            gatewayPaymentId: $request->gatewayPaymentId ?? ('COD-' . $request->merchantReference),
            gatewayTransactionId: $request->gatewayTransactionId ?? ('COD-TRX-' . $request->merchantReference),
            amountPaisa: $amountPaisa,
            currency: 'BDT',
            rawRequest: ['merchant_reference' => $request->merchantReference],
            rawResponse: ['status' => 'collected']
        );
    }

    public function refund(PaymentRefundRequest $request): PaymentRefundResult
    {
        return new PaymentRefundResult(
            successful: true,
            gateway: 'cod',
            gatewayRefundId: 'COD-REF-' . $request->merchantReference,
            refundedAmountPaisa: $request->refundAmountPaisa,
            currency: $request->currency,
            status: 'completed',
            rawRequest: [
                'merchant_reference' => $request->merchantReference,
                'amount_paisa'       => $request->refundAmountPaisa,
                'reason'             => $request->reason,
            ],
            rawResponse: ['status' => 'manual_cod_refund_recorded']
        );
    }

    public function parseWebhook(array $payload, array $headers = [], string $rawBody = ''): WebhookNotificationPayload
    {
        return new WebhookNotificationPayload(
            gateway: 'cod',
            gatewayEventId: (string) ($payload['event_id'] ?? ('cod-evt-' . ($payload['merchant_reference'] ?? uniqid()))),
            merchantReference: (string) ($payload['merchant_reference'] ?? ''),
            gatewayPaymentId: $payload['payment_id'] ?? null,
            gatewayTransactionId: $payload['trx_id'] ?? null,
            eventType: 'cod.collected',
            rawPayload: $payload
        );
    }
}

