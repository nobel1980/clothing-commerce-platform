<?php

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\RoleService;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Determine whether the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view') || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view') || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can('roles.manage') || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the role.
     */
    public function update(User $user, Role $role): bool
    {
        if ($role->name === 'super_admin' && !$user->isSuperAdmin()) {
            return false;
        }

        return $user->can('roles.manage') || $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can delete the role.
     */
    public function delete(User $user, Role $role): bool
    {
        // Reserved system roles cannot be deleted
        if (in_array($role->name, RoleService::SYSTEM_ROLES, true)) {
            return false;
        }

        return $user->can('roles.manage') || $user->isSuperAdmin();
    }
}
