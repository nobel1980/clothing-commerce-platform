<?php

namespace App\Shared\Contracts\Inventory;

final readonly class ReserveStockItemData
{
    public function __construct(
        public int $productVariantId,
        public int $quantity,
        public ?int $warehouseId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'product_variant_id' => $this->productVariantId,
            'quantity'           => $this->quantity,
            'warehouse_id'       => $this->warehouseId,
        ];
    }
}

