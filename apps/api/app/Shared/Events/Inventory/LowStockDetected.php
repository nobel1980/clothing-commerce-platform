<?php

namespace App\Shared\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $inventoryItemId,
        public readonly int $productVariantId,
        public readonly string $sku,
        public readonly int $warehouseId,
        public readonly string $warehouseCode,
        public readonly int $quantityOnHand,
        public readonly int $quantityReserved,
        public readonly int $availableQuantity,
        public readonly int $lowStockThreshold,
    ) {}
}

