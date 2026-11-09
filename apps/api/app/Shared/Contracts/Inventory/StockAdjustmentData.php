<?php

namespace App\Shared\Contracts\Inventory;

final readonly class StockAdjustmentData
{
    public function __construct(
        public int $productVariantId,
        public int $warehouseId,
        public int $quantityDelta,
        public string $type = 'adjustment',
        public ?string $reason = null,
        public ?string $notes = null,
        public ?string $referenceType = null,
        public ?string $referenceId = null,
        public ?int $performedBy = null,
    ) {}
}

