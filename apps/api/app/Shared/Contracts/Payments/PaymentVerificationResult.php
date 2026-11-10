<?php

namespace App\Shared\Contracts\Payments;

final readonly class PaymentVerificationResult
{
    public function __construct(
        public bool $verified,
        public string $gateway,
        public string $status, // 'paid', 'failed', 'pending', 'cancelled'
        public string $merchantReference,
        public ?string $gatewayPaymentId,
        public ?string $gatewayTransactionId,
        public int $amountPaisa,
        public string $currency = 'BDT',
        public ?string $merchantId = null,
        public array $rawRequest = [],
        public array $rawResponse = [],
        public ?string $failureReason = null,
    ) {}
}

