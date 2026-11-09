<?php

namespace App\Shared\Contracts\Inventory;

final readonly class StockReservationItemData
{
    public function __construct(
        public int $id,
        public int $stockReservationId,
        public int $inventoryItemId,
        public int $productVariantId,
        public int $warehouseId,
        public int $quantity,
    ) {}

    public function toArray(): array
    {
        return [
            'id'                   => $this->id,
            'stock_reservation_id' => $this->stockReservationId,
            'inventory_item_id'    => $this->inventoryItemId,
            'product_variant_id'   => $this->productVariantId,
            'warehouse_id'         => $this->warehouseId,
            'quantity'             => $this->quantity,
        ];
    }
}

