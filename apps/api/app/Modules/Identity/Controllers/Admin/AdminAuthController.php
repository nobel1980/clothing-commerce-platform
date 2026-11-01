<?php

namespace App\Modules\Identity\Controllers\Admin;

use App\Modules\Identity\Requests\AdminLoginRequest;
use App\Modules\Identity\Resources\UserResource;
use App\Modules\Identity\Services\AdminAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminAuthController extends Controller
{
    public function __construct(
        protected AdminAuthService $adminAuthService
    ) {}

    /**
     * POST /api/admin/auth/login
     */
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $user = $this->adminAuthService->login(
            $request->input('email'),
            $request->input('password'),
            $request->boolean('remember'),
            $request
        );

        $token = $user->createToken('admin_staff_token')->plainTextToken;

        return (new UserResource($user->load(['roles', 'permissions'])))
            ->additional([
                'token' => $token,
                'message' => 'Staff authentication successful.',
            ])
            ->response();
    }

    /**
     * GET /api/admin/auth/me
     */
    public function me(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load(['roles', 'permissions'])
        );
    }

    /**
     * POST /api/admin/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->adminAuthService->logout($request);

        return response()->json([
            'message' => 'Staff session successfully terminated.',
        ]);
    }
}
