<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AddressService
{
    /**
     * Get all addresses for a customer.
     */
    public function getUserAddresses(User $user): Collection
    {
        return $user->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Create a new address for a user.
     */
    public function createAddress(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data) {
            $isFirst = $user->addresses()->count() === 0;
            $shouldBeDefault = ($data['is_default'] ?? false) || $isFirst;

            if ($shouldBeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'division' => $data['division'],
                'district' => $data['district'],
                'upazila_thana' => $data['upazila_thana'],
                'street_address' => $data['street_address'],
                'postal_code' => $data['postal_code'] ?? null,
                'type' => $data['type'] ?? 'shipping',
                'is_default' => $shouldBeDefault,
                'delivery_instructions' => $data['delivery_instructions'] ?? null,
            ]);
        });
    }

    /**
     * Update an address.
     */
    public function updateAddress(Address $address, array $data): Address
    {
        return DB::transaction(function () use ($address, $data) {
            if ($data['is_default'] ?? false) {
                $address->user->addresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $address->update($data);

            return $address->fresh();
        });
    }

    /**
     * Set an address as the default address.
     */
    public function setDefaultAddress(Address $address): Address
    {
        return DB::transaction(function () use ($address) {
            $address->user->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);

            return $address->fresh();
        });
    }

    /**
     * Delete an address.
     */
    public function deleteAddress(Address $address): void
    {
        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $userId = $address->user_id;

            $address->delete();

            // If the deleted address was the default, assign the most recent address as default
            if ($wasDefault) {
                Address::where('user_id', $userId)
                    ->latest()
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });
    }
}
