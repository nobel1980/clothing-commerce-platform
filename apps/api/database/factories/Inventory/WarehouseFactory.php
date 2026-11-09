<?php

namespace Database\Factories\Inventory;

use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name'           => $this->faker->city() . ' Fulfillment Center',
            'code'           => strtoupper($this->faker->unique()->bothify('WH-???-###')),
            'address_line_1' => $this->faker->streetAddress(),
            'city'           => $this->faker->city(),
            'state'          => $this->faker->state(),
            'postal_code'    => $this->faker->postcode(),
            'country'        => 'BD',
            'phone'          => '+88017' . $this->faker->numerify('########'),
            'email'          => $this->faker->safeEmail(),
            'is_active'      => true,
            'is_default'     => false,
            'priority'       => 10,
        ];
    }

    public function defaultWarehouse(): static
    {
        return $this->state(fn () => [
            'is_default' => true,
            'is_active'  => true,
            'priority'   => 1,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active'  => false,
            'is_default' => false,
        ]);
    }
}

