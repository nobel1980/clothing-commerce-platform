<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;

Route::get('/health', function () {
    $checks = [
        'database' => ['status' => 'fail', 'message' => 'Not checked'],
        'redis' => ['status' => 'fail', 'message' => 'Not checked'],
        'queue' => ['status' => 'ok', 'connection' => config('queue.default', 'sync')],
    ];

    $isHealthy = true;

    // Database Check
    try {
        DB::connection()->getPdo();
        $checks['database'] = [
            'status' => 'ok',
            'driver' => config('database.default'),
            'database' => config('database.connections.' . config('database.default') . '.database'),
        ];
    } catch (\Throwable $e) {
        $checks['database'] = [
            'status' => 'fail',
            'message' => $e->getMessage(),
        ];
        $isHealthy = false;
    }

    // Redis Check
    try {
        if (config('database.redis.client')) {
            $redis = Redis::connection();
            $redis->ping();
            $checks['redis'] = [
                'status' => 'ok',
                'client' => config('database.redis.client'),
            ];
        } else {
            $checks['redis'] = [
                'status' => 'skipped',
                'message' => 'Redis client not configured',
            ];
        }
    } catch (\Throwable $e) {
        $checks['redis'] = [
            'status' => 'fail',
            'message' => $e->getMessage(),
        ];
    }

    $statusCode = $isHealthy ? 200 : 503;

    return response()->json([
        'status' => $isHealthy ? 'healthy' : 'degraded',
        'service' => 'clothing-commerce-api',
        'framework' => 'Laravel ' . app()->version(),
        'php_version' => PHP_VERSION,
        'timestamp' => now()->toIso8601String(),
        'checks' => $checks,
    ], $statusCode);
});

Route::get('/version', function () {
    return response()->json([
        'service' => 'clothing-commerce-api',
        'framework' => 'Laravel ' . app()->version(),
        'version' => '0.1.0',
        'php' => PHP_VERSION,
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Identity — Customer Authentication (Public & Stateful/Token)
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', [\App\Modules\Identity\Controllers\Customer\AuthController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('register');

    Route::post('login', [\App\Modules\Identity\Controllers\Customer\AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    Route::post('forgot-password', [\App\Modules\Identity\Controllers\Customer\AuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,1')
        ->name('forgot-password');

    Route::post('reset-password', [\App\Modules\Identity\Controllers\Customer\AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1')
        ->name('reset-password');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [\App\Modules\Identity\Controllers\Customer\AuthController::class, 'logout'])
            ->name('logout');
        Route::get('me', [\App\Modules\Identity\Controllers\Customer\AuthController::class, 'me'])
            ->name('me');
        Route::post('email/verification-notification', [\App\Modules\Identity\Controllers\Customer\AuthController::class, 'sendVerificationNotification'])
            ->name('verification.send');
    });
});

Route::get('auth/email/verify/{id}/{hash}', [\App\Modules\Identity\Controllers\Customer\AuthController::class, 'verifyEmail'])
    ->name('verification.verify');


// ─────────────────────────────────────────────────────────────────────────────
// Identity — Customer Account (Profile & Address Management)
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('account')->name('account.')->middleware('auth:sanctum')->group(function () {
    Route::get('profile', [\App\Modules\Identity\Controllers\Customer\ProfileController::class, 'show'])
        ->name('profile.show');
    Route::put('profile', [\App\Modules\Identity\Controllers\Customer\ProfileController::class, 'update'])
        ->name('profile.update');
    Route::post('change-password', [\App\Modules\Identity\Controllers\Customer\ProfileController::class, 'changePassword'])
        ->name('change-password');

    Route::post('addresses/{address}/default', [\App\Modules\Identity\Controllers\Customer\AddressController::class, 'setDefault'])
        ->name('addresses.default');
    Route::apiResource('addresses', \App\Modules\Identity\Controllers\Customer\AddressController::class)
        ->names('addresses');
});

// ─────────────────────────────────────────────────────────────────────────────
// Identity — Admin Authentication (Store Staff Portal)
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('admin/auth')->name('admin.auth.')->group(function () {
    Route::post('login', [\App\Modules\Identity\Controllers\Admin\AdminAuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login');

    Route::middleware(['auth:sanctum', 'staff'])->group(function () {
        Route::get('me', [\App\Modules\Identity\Controllers\Admin\AdminAuthController::class, 'me'])
            ->name('me');
        Route::post('logout', [\App\Modules\Identity\Controllers\Admin\AdminAuthController::class, 'logout'])
            ->name('logout');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Identity — Admin Management (Users, Roles, Permissions & Audit Trail)
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'staff'])->group(function () {
    // User Management
    Route::post('users/{user}/status', [\App\Modules\Identity\Controllers\Admin\AdminUserController::class, 'toggleStatus'])
        ->name('users.status');
    Route::apiResource('users', \App\Modules\Identity\Controllers\Admin\AdminUserController::class)
        ->names('users');

    // Role & Permission Management
    Route::get('permissions', [\App\Modules\Identity\Controllers\Admin\AdminRoleController::class, 'permissions'])
        ->name('permissions.index');
    Route::apiResource('roles', \App\Modules\Identity\Controllers\Admin\AdminRoleController::class)
        ->names('roles');

    // Audit Logs
    Route::get('audit-logs', [\App\Modules\Identity\Controllers\Admin\AdminAuditLogController::class, 'index'])
        ->name('audit-logs.index');
    Route::get('audit-logs/{auditLog}', [\App\Modules\Identity\Controllers\Admin\AdminAuditLogController::class, 'show'])
        ->name('audit-logs.show');
});

