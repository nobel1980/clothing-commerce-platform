<?php

namespace Database\Factories\Identity;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $bdPrefixes = ['017', '018', '019', '013', '014', '015', '016'];
        $prefix = fake()->randomElement($bdPrefixes);
        $phone = $prefix . fake()->numerify('########');

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => $phone,
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
            'avatar_url' => fake()->imageUrl(200, 200, 'people'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    /**
     * Indicate that the user is a super admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'super_admin',
        ])->afterCreating(function (User $user) {
            $user->assignRole('super_admin');
        });
    }

    /**
     * Staff role factory state helper.
     */
    public function staff(string $role = 'product_manager'): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ])->afterCreating(function (User $user) use ($role) {
            $user->assignRole($role);
        });
    }

    /**
     * Customer state helper.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'customer',
        ])->afterCreating(function (User $user) {
            $user->assignRole('customer');
        });
    }
}
