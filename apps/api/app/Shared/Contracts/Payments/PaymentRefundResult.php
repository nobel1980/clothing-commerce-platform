<?php

namespace App\Shared\Contracts\Payments;

final readonly class PaymentRefundResult
{
    public function __construct(
        public bool $successful,
        public string $gateway,
        public ?string $gatewayRefundId,
        public int $refundedAmountPaisa,
        public string $currency = 'BDT',
        public string $status = 'completed',
        public array $rawRequest = [],
        public array $rawResponse = [],
        public ?string $errorMessage = null,
    ) {}
}

