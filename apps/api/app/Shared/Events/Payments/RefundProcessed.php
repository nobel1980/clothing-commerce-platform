<?php

namespace App\Shared\Events\Payments;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundProcessed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly int $paymentRefundId,
        public readonly string $gateway,
        public readonly int $amountPaisa,
        public readonly string $currency = 'BDT',
    ) {}
}

