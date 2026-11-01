<?php

namespace App\Modules\Identity\Controllers\Customer;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Requests\LoginRequest;
use App\Modules\Identity\Requests\RegisterRequest;
use App\Modules\Identity\Requests\ResetPasswordRequest;
use App\Modules\Identity\Resources\UserResource;
use App\Modules\Identity\Services\AuthService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * POST /api/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated(), $request);
        $token = $user->createToken('customer_token')->plainTextToken;

        return (new UserResource($user->load(['roles', 'permissions'])))
            ->additional([
                'token' => $token,
                'message' => 'Registration successful. Welcome to our store!',
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login(
            $request->input('email'),
            $request->input('password'),
            $request->boolean('remember'),
            $request
        );

        $token = $user->createToken('customer_token')->plainTextToken;

        return (new UserResource($user->load(['roles', 'permissions', 'addresses'])))
            ->additional([
                'token' => $token,
                'message' => 'Login successful.',
            ])
            ->response();
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request);

        return response()->json([
            'message' => 'Successfully logged out.',
        ]);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load(['roles', 'permissions', 'addresses'])
        );
    }

    /**
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = strtolower(trim($request->input('email')));
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // In local/test environment, return the token for ease of testing
        $data = [
            'message' => 'Password reset link sent to your email address.',
        ];

        if (app()->environment('local', 'testing')) {
            $data['reset_token'] = $token;
        }

        return response()->json($data);
    }

    /**
     * POST /api/auth/reset-password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $email = strtolower(trim($request->input('email')));
        $token = $request->input('token');
        $newPassword = $request->input('password');

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$record || !Hash::check($token, $record->token)) {
            throw ValidationException::withMessages([
                'email' => ['This password reset token is invalid or has expired.'],
            ]);
        }

        // Expire after 60 minutes
        if (now()->subMinutes(60)->greaterThan($record->created_at)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw ValidationException::withMessages([
                'email' => ['This password reset token has expired. Please request a new one.'],
            ]);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($newPassword),
            'remember_token' => Str::random(60),
        ])->save();

        $user->tokens()->delete();
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        event(new PasswordReset($user));

        return response()->json([
            'message' => 'Password has been reset successfully. Please log in with your new password.',
        ]);
    }

    /**
     * POST /api/auth/email/verification-notification
     */
    public function sendVerificationNotification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified.',
            ]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification link sent to your email address.',
        ]);
    }

    /**
     * POST /api/auth/email/verify/{id}/{hash}
     */
    public function verifyEmail(Request $request, $id, $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Invalid verification link or signature.',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified.',
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'message' => 'Email has been successfully verified.',
        ]);
    }
}
