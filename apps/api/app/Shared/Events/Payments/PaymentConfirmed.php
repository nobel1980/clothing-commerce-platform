<?php

namespace App\Shared\Events\Payments;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly int $paymentTransactionId,
        public readonly string $gateway,
        public readonly string $gatewayTransactionId,
        public readonly int $amountPaisa,
        public readonly string $currency = 'BDT',
    ) {}
}

