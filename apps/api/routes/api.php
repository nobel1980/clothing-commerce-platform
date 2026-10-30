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
