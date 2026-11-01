<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'parent_id'        => null,
            'name'             => ucwords($name),
            'slug'             => Str::slug($name),
            'description'      => $this->faker->sentence(),
            'image_url'        => null,
            'sort_order'       => $this->faker->numberBetween(0, 10),
            'is_active'        => true,
            'meta_title'       => null,
            'meta_description' => null,
        ];
    }
}
