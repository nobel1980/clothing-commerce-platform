<?php

namespace App\Shared\Events\Checkout;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly ?int $userId,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly string $paymentMethod,
        public readonly string $status,
    ) {}
}

