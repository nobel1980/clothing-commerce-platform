<?php

namespace App\Modules\Identity\Controllers\Admin;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Requests\CreateUserRequest;
use App\Modules\Identity\Requests\UpdateUserRequest;
use App\Modules\Identity\Resources\UserResource;
use App\Modules\Identity\Services\AuditService;
use App\Modules\Identity\Services\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected UserService $userService,
        protected AuditService $auditService
    ) {}

    /**
     * GET /api/admin/users
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only(['search', 'role', 'status']);
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        $users = $this->userService->listUsers($filters, $perPage);

        return UserResource::collection($users);
    }

    /**
     * POST /api/admin/users
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->userService->createUser($request->validated(), $request->user());

        return (new UserResource($user->load(['roles', 'permissions'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/admin/users/{user}
     */
    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource(
            $user->load(['roles', 'permissions', 'addresses'])
        );
    }

    /**
     * PUT /api/admin/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $updated = $this->userService->updateUser($user, $request->validated(), $request->user());

        return new UserResource($updated);
    }

    /**
     * DELETE /api/admin/users/{user}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $actor = $request->user();

        if ($user->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own account.'],
            ]);
        }

        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'user' => ['Super Administrator accounts cannot be deleted.'],
            ]);
        }

        $oldValues = [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
        ];

        $user->tokens()->delete();
        $user->delete();

        $this->auditService->log('user.deleted', $actor, null, $oldValues, null);

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    /**
     * POST /api/admin/users/{user}/status
     */
    public function toggleStatus(Request $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $request->validate([
            'status' => ['required', 'in:active,suspended,inactive'],
        ]);

        $updated = $this->userService->toggleStatus($user, $request->input('status'), $request->user());

        return new UserResource($updated->load(['roles', 'permissions']));
    }
}
