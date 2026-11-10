<?php

namespace App\Shared\Events\Shipping;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParcelReturned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $shipmentId,
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly string $courier,
        public readonly string $consignmentId,
        public readonly ?string $reason = null,
    ) {}
}

