<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List users with optional search and role filtering.
     */
    public function listUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with(['roles', 'permissions']);

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('phone', 'like', $search);
            });
        }

        if (!empty($filters['role'])) {
            $query->role($filters['role']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Create a new staff or customer user.
     */
    public function createUser(array $data, User $actor): User
    {
        $role = $data['role'] ?? 'customer';
        $this->ensureCanAssignRole($role, $actor);

        return DB::transaction(function () use ($data, $role, $actor) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower(trim($data['email'])),
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => $role,
                'status' => $data['status'] ?? 'active',
                'email_verified_at' => ($data['verified'] ?? false) ? now() : null,
            ]);

            $user->assignRole($role);

            $this->auditService->log('user.created', $actor, $user, null, [
                'email' => $user->email,
                'role' => $role,
                'status' => $user->status,
            ]);

            return $user;
        });
    }

    /**
     * Update an existing user while enforcing privilege boundaries.
     */
    public function updateUser(User $target, array $data, User $actor): User
    {
        // Prevent modifying self status or self role
        if ($target->id === $actor->id) {
            if (isset($data['status']) && $data['status'] !== $target->status) {
                throw ValidationException::withMessages([
                    'status' => ['You cannot change your own account status.'],
                ]);
            }

            if (isset($data['role']) && !$target->hasRole($data['role'])) {
                throw ValidationException::withMessages([
                    'role' => ['You cannot change your own administrative roles.'],
                ]);
            }
        }

        // Prevent non-super_admin from modifying super_admin users
        if ($target->isSuperAdmin() && !$actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'user' => ['Only Super Administrators can modify Super Admin accounts.'],
            ]);
        }

        if (!empty($data['role'])) {
            $this->ensureCanAssignRole($data['role'], $actor);
        }

        return DB::transaction(function () use ($target, $data, $actor) {
            $oldValues = [
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'role' => $target->roles->first()?->name,
                'status' => $target->status,
            ];

            $updateData = [
                'name' => $data['name'] ?? $target->name,
                'phone' => $data['phone'] ?? $target->phone,
            ];

            if (!empty($data['email'])) {
                $updateData['email'] = strtolower(trim($data['email']));
            }

            if (!empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            if (!empty($data['status'])) {
                $updateData['status'] = $data['status'];
            }

            if (!empty($data['role'])) {
                $updateData['role'] = $data['role'];
                $target->syncRoles([$data['role']]);
            }

            $target->update($updateData);

            $newValues = [
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'role' => $target->roles->first()?->name,
                'status' => $target->status,
            ];

            $this->auditService->log('user.updated', $actor, $target, $oldValues, $newValues);

            return $target->fresh(['roles', 'permissions']);
        });
    }

    /**
     * Toggle a user status (active, suspended, inactive).
     */
    public function toggleStatus(User $target, string $status, User $actor): User
    {
        if ($target->id === $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot change your own account status.'],
            ]);
        }

        if ($target->isSuperAdmin() && !$actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'status' => ['Only Super Administrators can modify Super Admin status.'],
            ]);
        }

        $oldStatus = $target->status;
        $target->update(['status' => $status]);

        $this->auditService->log("user.status_{$status}", $actor, $target, ['status' => $oldStatus], ['status' => $status]);

        return $target;
    }

    /**
     * Validate that the actor is authorized to assign the requested role.
     */
    protected function ensureCanAssignRole(string $role, User $actor): void
    {
        if ($role === 'super_admin' && !$actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => ['Privilege escalation prevented. Only Super Administrators can assign the Super Admin role.'],
            ]);
        }
    }
}
