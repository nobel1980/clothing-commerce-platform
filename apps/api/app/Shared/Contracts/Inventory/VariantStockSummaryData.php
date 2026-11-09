<?php

namespace App\Shared\Contracts\Inventory;

final readonly class VariantStockSummaryData
{
    /**
     * @param array<int, array{
     *     warehouse_id: int,
     *     warehouse_code: string,
     *     warehouse_name: string,
     *     quantity_on_hand: int,
     *     quantity_reserved: int,
     *     available_quantity: int,
     *     is_low_stock: bool
     * }> $warehouseBreakdown
     */
    public function __construct(
        public int $productVariantId,
        public int $totalOnHand,
        public int $totalReserved,
        public int $totalAvailable,
        public bool $inStock,
        public bool $isLowStock,
        public array $warehouseBreakdown = [],
    ) {}

    public function toArray(): array
    {
        return [
            'product_variant_id'  => $this->productVariantId,
            'total_on_hand'       => $this->totalOnHand,
            'total_reserved'      => $this->totalReserved,
            'total_available'     => $this->totalAvailable,
            'in_stock'            => $this->inStock,
            'is_low_stock'        => $this->isLowStock,
            'warehouse_breakdown' => $this->warehouseBreakdown,
        ];
    }
}

