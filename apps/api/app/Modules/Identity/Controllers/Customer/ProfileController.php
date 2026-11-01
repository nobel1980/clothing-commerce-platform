<?php

namespace App\Modules\Identity\Controllers\Customer;

use App\Modules\Identity\Requests\ChangePasswordRequest;
use App\Modules\Identity\Requests\UpdateProfileRequest;
use App\Modules\Identity\Resources\UserResource;
use App\Modules\Identity\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * GET /api/account/profile
     */
    public function show(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load(['roles', 'permissions', 'addresses'])
        );
    }

    /**
     * PUT /api/account/profile
     */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $oldValues = [
            'name' => $user->name,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
        ];

        $user->update($request->validated());

        $this->auditService->log('customer.profile_updated', $user, $user, $oldValues, [
            'name' => $user->name,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
        ]);

        return new UserResource($user->fresh(['roles', 'permissions', 'addresses']));
    }

    /**
     * POST /api/account/change-password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        // Revoke all tokens except current one if desired, or all tokens for security
        $user->tokens()->delete();
        $newToken = $user->createToken('customer_token')->plainTextToken;

        $this->auditService->log('customer.password_changed', $user, $user);

        return response()->json([
            'message' => 'Password has been updated successfully.',
            'token' => $newToken,
        ]);
    }
}
