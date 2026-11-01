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
// Catalog — Public Storefront (no auth required)
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('catalog')->name('catalog.')->group(function () {
    // Categories
    Route::get('categories', [\App\Modules\Catalog\Controllers\Store\StoreCategoryController::class, 'index'])
        ->name('categories.index');
    Route::get('categories/{category:slug}', [\App\Modules\Catalog\Controllers\Store\StoreCategoryController::class, 'show'])
        ->name('categories.show');

    // Products
    Route::get('products', [\App\Modules\Catalog\Controllers\Store\StoreProductController::class, 'index'])
        ->name('products.index');
    Route::get('products/{product:slug}', [\App\Modules\Catalog\Controllers\Store\StoreProductController::class, 'show'])
        ->name('products.show');
});

// ─────────────────────────────────────────────────────────────────────────────
// Catalog — Admin Backoffice (requires authentication + staff role)
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('admin/catalog')
    ->name('admin.catalog.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // Categories
        Route::get('categories/tree', [\App\Modules\Catalog\Controllers\Admin\AdminCategoryController::class, 'tree'])
            ->name('categories.tree');
        Route::apiResource('categories', \App\Modules\Catalog\Controllers\Admin\AdminCategoryController::class)
            ->names('categories');

        // Products
        Route::post('products/import', [\App\Modules\Catalog\Controllers\Admin\AdminProductController::class, 'import'])
            ->name('products.import');
        Route::post('products/{product}/publish', [\App\Modules\Catalog\Controllers\Admin\AdminProductController::class, 'publish'])
            ->name('products.publish');
        Route::post('products/{product}/unpublish', [\App\Modules\Catalog\Controllers\Admin\AdminProductController::class, 'unpublish'])
            ->name('products.unpublish');

        // Variants (nested under products)
        Route::post('products/{product}/variants', [\App\Modules\Catalog\Controllers\Admin\AdminProductController::class, 'storeVariant'])
            ->name('products.variants.store');
        Route::put('products/{product}/variants/{variant}', [\App\Modules\Catalog\Controllers\Admin\AdminProductController::class, 'updateVariant'])
            ->name('products.variants.update');
        Route::delete('products/{product}/variants/{variant}', [\App\Modules\Catalog\Controllers\Admin\AdminProductController::class, 'destroyVariant'])
            ->name('products.variants.destroy');

        // Images (nested under products)
        Route::post('products/{product}/images', [\App\Modules\Catalog\Controllers\Admin\AdminProductController::class, 'uploadImage'])
            ->name('products.images.store');
        Route::post('products/{product}/images/reorder', [\App\Modules\Catalog\Controllers\Admin\AdminProductController::class, 'reorderImages'])
            ->name('products.images.reorder');
        Route::delete('products/{product}/images/{image}', [\App\Modules\Catalog\Controllers\Admin\AdminProductController::class, 'destroyImage'])
            ->name('products.images.destroy');

        Route::apiResource('products', \App\Modules\Catalog\Controllers\Admin\AdminProductController::class)
            ->names('products');
    });
