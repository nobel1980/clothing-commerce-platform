<?php

namespace App\Shared\Contracts\Payments;

final readonly class PaymentVerificationRequest
{
    public function __construct(
        public string $merchantReference,
        public ?string $gatewayPaymentId = null,
        public ?string $gatewayTransactionId = null,
        public array $callbackPayload = [],
    ) {}
}

