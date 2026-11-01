<?php

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view') || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the specific user.
     */
    public function view(User $user, User $target): bool
    {
        return $user->id === $target->id || $user->can('users.view') || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can('users.manage') || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the target user.
     */
    public function update(User $user, User $target): bool
    {
        // Target is super admin: only super admin can modify
        if ($target->isSuperAdmin() && !$user->isSuperAdmin()) {
            return false;
        }

        return $user->id === $target->id || $user->can('users.manage') || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can delete the target user.
     */
    public function delete(User $user, User $target): bool
    {
        // Cannot delete oneself
        if ($user->id === $target->id) {
            return false;
        }

        // Cannot delete a super admin unless the actor is a super admin
        if ($target->isSuperAdmin()) {
            return false;
        }

        return $user->can('users.manage') || $user->isSuperAdmin();
    }
}
