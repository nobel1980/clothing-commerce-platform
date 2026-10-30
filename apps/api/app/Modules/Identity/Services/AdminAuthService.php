<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminAuthService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Authenticate administrative staff.
     */
    public function login(string $email, string $password, bool $remember, Request $request): User
    {
        $throttleKey = 'admin|' . Str::lower($email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->auditService->log('admin.login_throttled', null, null, null, [
                'email' => $email,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => [trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)])],
            ]);
        }

        $user = User::where('email', Str::lower($email))->first();

        if (!$user || !Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            $this->auditService->log('admin.login_failed', null, null, null, [
                'email' => $email,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        // Verify user has administrative staff role
        if (!$user->isStaff()) {
            RateLimiter::hit($throttleKey, 60);

            $this->auditService->log('admin.access_denied_non_staff', $user, $user, null, [
                'email' => $email,
            ]);

            throw ValidationException::withMessages([
                'email' => ['Access denied. This portal is restricted to authorized store staff.'],
            ]);
        }

        if (!$user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['This staff account has been suspended or deactivated.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        if ($request->hasSession()) {
            Auth::login($user, $remember);
            $request->session()->regenerate();
        }

        $this->auditService->log('admin.logged_in', $user, $user, null, [
            'roles' => $user->getRoleNames()->toArray(),
        ]);

        return $user;
    }

    /**
     * Log out administrative staff.
     */
    public function logout(Request $request): void
    {
        $user = $request->user();

        if ($user) {
            $this->auditService->log('admin.logged_out', $user, $user);
            $user->tokens()->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
