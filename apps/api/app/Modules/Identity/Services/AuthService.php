<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Register a new customer.
     */
    public function register(array $data, Request $request): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'customer',
            'status' => 'active',
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $user->assignRole('customer');

        event(new Registered($user));

        if ($request->hasSession()) {
            Auth::login($user, true);
            $request->session()->regenerate();
        }

        $this->auditService->log('customer.registered', $user, $user, null, [
            'email' => $user->email,
            'name' => $user->name,
        ]);

        return $user;
    }

    /**
     * Authenticate customer with rate-limiting and secure session.
     */
    public function login(string $login, string $password, bool $remember, Request $request): User
    {
        $throttleKey = Str::lower($login) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => [trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)])],
            ]);
        }

        // Support login via email or Bangladesh phone number
        $user = User::where('email', Str::lower($login))
            ->orWhere('phone', $login)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        if (!$user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been suspended or is inactive. Please contact customer care.'],
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

        $this->auditService->log('customer.logged_in', $user, $user);

        return $user;
    }

    /**
     * Log the customer out securely.
     */
    public function logout(Request $request): void
    {
        $user = $request->user();

        if ($user) {
            $this->auditService->log('customer.logged_out', $user, $user);
            $user->tokens()->delete(); // Invalidate API tokens if any
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
