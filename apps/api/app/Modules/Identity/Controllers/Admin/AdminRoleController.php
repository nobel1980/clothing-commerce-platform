<?php

namespace App\Modules\Identity\Controllers\Admin;

use App\Modules\Identity\Requests\CreateRoleRequest;
use App\Modules\Identity\Requests\UpdateRoleRequest;
use App\Modules\Identity\Resources\PermissionResource;
use App\Modules\Identity\Resources\RoleResource;
use App\Modules\Identity\Services\RoleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Spatie\Permission\Models\Role;

class AdminRoleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected RoleService $roleService
    ) {}

    /**
     * GET /api/admin/roles
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        $roles = $this->roleService->listRoles();

        return RoleResource::collection($roles);
    }

    /**
     * POST /api/admin/roles
     */
    public function store(CreateRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $role = $this->roleService->createRole(
            $request->input('name'),
            $request->input('permissions', []),
            $request->user()
        );

        return (new RoleResource($role))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/admin/roles/{role}
     */
    public function show(Role $role): RoleResource
    {
        $this->authorize('view', $role);

        return new RoleResource($role->load('permissions'));
    }

    /**
     * PUT /api/admin/roles/{role}
     */
    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $this->authorize('update', $role);

        $updated = $this->roleService->updateRole(
            $role,
            $request->input('permissions', []),
            $request->user()
        );

        return new RoleResource($updated);
    }

    /**
     * DELETE /api/admin/roles/{role}
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        $this->roleService->deleteRole($role, $request->user());

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }

    /**
     * GET /api/admin/permissions
     */
    public function permissions(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        $permissions = $this->roleService->listPermissions();

        return PermissionResource::collection($permissions);
    }
}
