<?php

namespace App\Shared\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockReservationExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $reservationId,
        public readonly string $idempotencyKey,
        public readonly ?string $referenceType,
        public readonly ?string $referenceId,
    ) {}
}

