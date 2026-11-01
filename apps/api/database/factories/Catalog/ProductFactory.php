<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'brand_id'         => null,
            'category_id'      => null,
            'name'             => ucwords($name),
            'slug'             => Str::slug($name),
            'description'      => $this->faker->paragraph(),
            'body'             => null,
            'status'           => 'draft',
            'is_featured'      => false,
            'published_at'     => null,
            'meta_title'       => null,
            'meta_description' => null,
            'meta_keywords'    => null,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);
    }
}
