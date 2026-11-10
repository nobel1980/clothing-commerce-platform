<?php

namespace App\Shared\Events\Orders;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly string $reason,
        public readonly ?int $actorId = null,
    ) {}
}

