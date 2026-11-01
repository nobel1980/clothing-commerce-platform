<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id'       => Product::factory(),
            'sku'              => strtoupper($this->faker->unique()->bothify('SKU-????-##')),
            'color'            => $this->faker->safeColorName(),
            'size'             => $this->faker->randomElement(['XS', 'S', 'M', 'L', 'XL', 'XXL']),
            // Prices as integer sub-units (paisa) — never floats
            'price_amount'     => $this->faker->numberBetween(50000, 500000),
            'compare_at_price' => null,
            'cost_price'       => null,
            'currency'         => 'BDT',
            'status'           => 'active',
        ];
    }
}
