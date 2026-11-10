<?php

namespace App\Shared\Contracts\Payments;

final readonly class PaymentInitiationResult
{
    public function __construct(
        public bool $successful,
        public string $gateway,
        public string $merchantReference,
        public ?string $gatewayPaymentId = null,
        public ?string $redirectUrl = null,
        public string $status = 'pending',
        public array $rawRequest = [],
        public array $rawResponse = [],
        public ?string $errorMessage = null,
    ) {}
}

