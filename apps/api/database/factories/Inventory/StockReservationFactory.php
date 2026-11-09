<?php

namespace Database\Factories\Inventory;

use App\Modules\Inventory\Models\StockReservation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StockReservationFactory extends Factory
{
    protected $model = StockReservation::class;

    public function definition(): array
    {
        return [
            'idempotency_key' => 'res-' . Str::uuid()->toString(),
            'reference_type'  => 'checkout',
            'reference_id'    => (string) Str::uuid(),
            'status'          => StockReservation::STATUS_ACTIVE,
            'expires_at'      => now()->addMinutes(15),
            'committed_at'    => null,
            'released_at'     => null,
            'expired_at'      => null,
            'metadata'        => [],
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status'     => StockReservation::STATUS_ACTIVE,
            'expires_at' => now()->subMinutes(5),
        ]);
    }
}

