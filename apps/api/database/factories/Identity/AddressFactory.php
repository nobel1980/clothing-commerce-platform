<?php

namespace Database\Factories\Identity;

use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Requests\AddressRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        $bdPrefixes = ['017', '018', '019', '013', '014', '015', '016'];
        $prefix = fake()->randomElement($bdPrefixes);
        $phone = $prefix . fake()->numerify('########');

        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'phone' => $phone,
            'division' => fake()->randomElement(AddressRequest::BD_DIVISIONS),
            'district' => fake()->city(),
            'upazila_thana' => fake()->word() . ' Thana',
            'street_address' => fake()->streetAddress() . ', Road #' . fake()->numberBetween(1, 20),
            'postal_code' => (string) fake()->numberBetween(1000, 9999),
            'type' => fake()->randomElement(['shipping', 'billing', 'both']),
            'is_default' => false,
            'delivery_instructions' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Set address as default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
