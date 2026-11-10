<?php

namespace App\Shared\Contracts\Payments;

final readonly class WebhookNotificationPayload
{
    public function __construct(
        public string $gateway,
        public string $gatewayEventId,
        public string $merchantReference,
        public ?string $gatewayPaymentId = null,
        public ?string $gatewayTransactionId = null,
        public ?string $eventType = 'payment.notification',
        public array $rawPayload = [],
    ) {}
}

