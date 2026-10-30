<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    public const SYSTEM_ROLES = [
        'super_admin',
        'product_manager',
        'inventory_manager',
        'order_manager',
        'customer_support',
        'marketing_manager',
        'report_viewer',
        'customer',
    ];

    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get all roles with permission relations.
     */
    public function listRoles(): Collection
    {
        return Role::with('permissions')->orderBy('name')->get();
    }

    /**
     * Get all permissions grouped by domain.
     */
    public function listPermissions(): Collection
    {
        return Permission::orderBy('name')->get();
    }

    /**
     * Create a new custom role.
     */
    public function createRole(string $name, array $permissions, User $actor): Role
    {
        if (in_array(strtolower($name), self::SYSTEM_ROLES, true)) {
            throw ValidationException::withMessages([
                'name' => ['Role name conflicts with reserved system role.'],
            ]);
        }

        $role = Role::create([
            'name' => strtolower(trim($name)),
            'guard_name' => 'web',
        ]);

        if (!empty($permissions)) {
            $role->syncPermissions($permissions);
        }

        $this->auditService->log('role.created', $actor, null, null, [
            'name' => $role->name,
            'permissions' => $permissions,
        ]);

        return $role->load('permissions');
    }

    /**
     * Update permissions for a role.
     */
    public function updateRole(Role $role, array $permissions, User $actor): Role
    {
        if ($role->name === 'super_admin' && !$actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => ['Only Super Administrators can modify the Super Admin role.'],
            ]);
        }

        $oldPermissions = $role->permissions->pluck('name')->toArray();
        $role->syncPermissions($permissions);

        $this->auditService->log('role.permissions_updated', $actor, null, [
            'role' => $role->name,
            'permissions' => $oldPermissions,
        ], [
            'role' => $role->name,
            'permissions' => $permissions,
        ]);

        return $role->load('permissions');
    }

    /**
     * Delete a role.
     */
    public function deleteRole(Role $role, User $actor): void
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => ["System role '{$role->name}' cannot be deleted."],
            ]);
        }

        $roleName = $role->name;
        $role->delete();

        $this->auditService->log('role.deleted', $actor, null, ['name' => $roleName]);
    }
}
