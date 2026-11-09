<?php

namespace Database\Factories\Inventory;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'product_variant_id'    => ProductVariant::factory(),
            'warehouse_id'          => Warehouse::factory(),
            'quantity_on_hand'      => 50,
            'quantity_reserved'     => 0,
            'low_stock_threshold'   => 5,
            'reorder_point'         => 10,
            'reorder_quantity'      => 50,
            'bin_location'          => strtoupper($this->faker->bothify('A#-R#-B##')),
            'low_stock_notified_at' => null,
        ];
    }

    public function lowStock(int $available = 3, int $threshold = 5): static
    {
        return $this->state(fn () => [
            'quantity_on_hand'    => $available,
            'quantity_reserved'   => 0,
            'low_stock_threshold' => $threshold,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => [
            'quantity_on_hand'  => 0,
            'quantity_reserved' => 0,
        ]);
    }
}

