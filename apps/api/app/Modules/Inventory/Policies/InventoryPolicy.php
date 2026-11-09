<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Identity\Models\User;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view')
            || $user->can('inventory.manage')
            || $user->isSuperAdmin();
    }

    public function manage(User $user): bool
    {
        return $user->can('inventory.manage') || $user->isSuperAdmin();
    }

    public function viewReports(User $user): bool
    {
        return $user->can('reports.view')
            || $user->can('inventory.view')
            || $user->can('inventory.manage')
            || $user->isSuperAdmin();
    }
}

