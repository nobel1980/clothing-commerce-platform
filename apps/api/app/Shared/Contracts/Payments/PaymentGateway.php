<?php

namespace App\Shared\Contracts\Payments;

interface PaymentGateway
{
    public function gatewayName(): string;

    public function initiate(PaymentInitiationRequest $request): PaymentInitiationResult;

    public function verify(PaymentVerificationRequest $request): PaymentVerificationResult;

    public function refund(PaymentRefundRequest $request): PaymentRefundResult;

    public function parseWebhook(array $payload, array $headers = [], string $rawBody = ''): WebhookNotificationPayload;
}

