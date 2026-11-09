<?php

namespace App\Shared\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockAdjusted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $inventoryMovementId,
        public readonly int $inventoryItemId,
        public readonly int $productVariantId,
        public readonly int $warehouseId,
        public readonly string $type,
        public readonly int $onHandDelta,
        public readonly int $reservedDelta,
        public readonly int $quantityOnHand,
        public readonly int $quantityReserved,
        public readonly int $availableQuantity,
    ) {}
}

