<?php

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\User;

class AddressPolicy
{
    /**
     * Determine whether the user can view the address.
     */
    public function view(User $user, Address $address): bool
    {
        return $user->id === $address->user_id || $user->isStaff();
    }

    /**
     * Determine whether the user can update the address.
     */
    public function update(User $user, Address $address): bool
    {
        return $user->id === $address->user_id || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can delete the address.
     */
    public function delete(User $user, Address $address): bool
    {
        return $user->id === $address->user_id || $user->isSuperAdmin();
    }
}
