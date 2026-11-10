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
    Route::get('orders', [\App\Modules\Checkout\Controllers\StoreCheckoutQuoteController::class, 'listOrders'])
        ->name('orders.index');
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

// ─────────────────────────────────────────────────────────────────────────────
// Inventory — Public Storefront Availability
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('variants/{variant}/availability', [\App\Modules\Inventory\Controllers\Store\StoreInventoryController::class, 'availability'])
        ->name('variants.availability');
});

// ─────────────────────────────────────────────────────────────────────────────
// Cart — Guest & Customer Cart Management, Merging, Address & Coupon Selection
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [\App\Modules\Cart\Controllers\CartController::class, 'show'])
        ->name('show');
    Route::delete('/', [\App\Modules\Cart\Controllers\CartController::class, 'clear'])
        ->name('clear');
    Route::post('items', [\App\Modules\Cart\Controllers\CartController::class, 'addItem'])
        ->name('items.store');
    Route::put('items/{item}', [\App\Modules\Cart\Controllers\CartController::class, 'updateItem'])
        ->name('items.update');
    Route::patch('items/{item}', [\App\Modules\Cart\Controllers\CartController::class, 'updateItem'])
        ->name('items.patch');
    Route::delete('items/{item}', [\App\Modules\Cart\Controllers\CartController::class, 'removeItem'])
        ->name('items.destroy');
    Route::put('address', [\App\Modules\Cart\Controllers\CartController::class, 'selectAddress'])
        ->name('address.update');
    Route::post('address', [\App\Modules\Cart\Controllers\CartController::class, 'selectAddress'])
        ->name('address.store');
    Route::post('coupon', [\App\Modules\Cart\Controllers\CartController::class, 'applyCoupon'])
        ->name('coupon.apply');
    Route::delete('coupon', [\App\Modules\Cart\Controllers\CartController::class, 'removeCoupon'])
        ->name('coupon.remove');
    Route::post('merge', [\App\Modules\Cart\Controllers\CartController::class, 'merge'])
        ->middleware('auth:sanctum')
        ->name('merge');
});

// ─────────────────────────────────────────────────────────────────────────────
// Checkout — Public Authoritative Quote, Coupon, Shipping & Order Placement
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('checkout')->name('checkout.')->group(function () {
    Route::post('quote', [\App\Modules\Checkout\Controllers\StoreCheckoutQuoteController::class, 'quote'])
        ->name('quote');
    Route::post('validate-coupon', [\App\Modules\Checkout\Controllers\StoreCheckoutQuoteController::class, 'validateCoupon'])
        ->name('validate-coupon');
    Route::post('shipping-rates', [\App\Modules\Checkout\Controllers\StoreCheckoutQuoteController::class, 'shippingRates'])
        ->name('shipping-rates');
    Route::post('orders', [\App\Modules\Checkout\Controllers\StoreCheckoutQuoteController::class, 'placeOrder'])
        ->name('orders.store');
    Route::get('orders/{orderNumber}', [\App\Modules\Checkout\Controllers\StoreCheckoutQuoteController::class, 'showOrder'])
        ->middleware('throttle:60,1')
        ->name('orders.show');
});

// ─────────────────────────────────────────────────────────────────────────────
// Inventory — Admin Backoffice (requires authentication + staff role)
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('admin/inventory')
    ->name('admin.inventory.')
    ->middleware(['auth:sanctum', 'staff'])
    ->group(function () {
        // Warehouses
        Route::apiResource('warehouses', \App\Modules\Inventory\Controllers\Admin\AdminWarehouseController::class)
            ->names('warehouses');

        // Inventory Items & Stock Operations
        Route::get('items', [\App\Modules\Inventory\Controllers\Admin\AdminInventoryController::class, 'index'])
            ->name('items.index');
        Route::get('items/{inventoryItem}', [\App\Modules\Inventory\Controllers\Admin\AdminInventoryController::class, 'show'])
            ->name('items.show');
        Route::put('items/{inventoryItem}', [\App\Modules\Inventory\Controllers\Admin\AdminInventoryController::class, 'update'])
            ->name('items.update');
        Route::get('variants/{variant}/stock', [\App\Modules\Inventory\Controllers\Admin\AdminInventoryController::class, 'variantStock'])
            ->name('variants.stock');

        Route::post('adjust', [\App\Modules\Inventory\Controllers\Admin\AdminInventoryController::class, 'adjust'])
            ->name('adjust');
        Route::post('set-stock', [\App\Modules\Inventory\Controllers\Admin\AdminInventoryController::class, 'setStock'])
            ->name('set-stock');
        Route::post('transfer', [\App\Modules\Inventory\Controllers\Admin\AdminInventoryController::class, 'transfer'])
            ->name('transfer');

        // Stock Movement History
        Route::get('movements', [\App\Modules\Inventory\Controllers\Admin\AdminInventoryController::class, 'movements'])
            ->name('movements.index');

        // Stock Reservations
        Route::post('reservations/expire-overdue', [\App\Modules\Inventory\Controllers\Admin\AdminReservationController::class, 'expireOverdue'])
            ->name('reservations.expire-overdue');
        Route::get('reservations', [\App\Modules\Inventory\Controllers\Admin\AdminReservationController::class, 'index'])
            ->name('reservations.index');
        Route::post('reservations', [\App\Modules\Inventory\Controllers\Admin\AdminReservationController::class, 'store'])
            ->name('reservations.store');
        Route::get('reservations/{reservation}', [\App\Modules\Inventory\Controllers\Admin\AdminReservationController::class, 'show'])
            ->name('reservations.show');
        Route::post('reservations/{reservation}/commit', [\App\Modules\Inventory\Controllers\Admin\AdminReservationController::class, 'commit'])
            ->name('reservations.commit');
        Route::post('reservations/{reservation}/release', [\App\Modules\Inventory\Controllers\Admin\AdminReservationController::class, 'release'])
            ->name('reservations.release');

        // Inventory Reporting
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('summary', [\App\Modules\Inventory\Controllers\Admin\AdminInventoryReportController::class, 'summary'])
                ->name('summary');
            Route::get('low-stock', [\App\Modules\Inventory\Controllers\Admin\AdminInventoryReportController::class, 'lowStock'])
                ->name('low-stock');
            Route::get('warehouses', [\App\Modules\Inventory\Controllers\Admin\AdminInventoryReportController::class, 'warehouses'])
                ->name('warehouses');
            Route::get('movements', [\App\Modules\Inventory\Controllers\Admin\AdminInventoryReportController::class, 'movements'])
                ->name('movements');
        });
    });

// ─────────────────────────────────────────────────────────────────────────────
// Orders — Customer Order History, Details & Cancellation
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('orders')->name('orders.')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [\App\Modules\Orders\Controllers\CustomerOrderController::class, 'index'])
        ->name('index');
    Route::get('{orderNumber}', [\App\Modules\Orders\Controllers\CustomerOrderController::class, 'show'])
        ->name('show');
    Route::post('{orderNumber}/cancel', [\App\Modules\Orders\Controllers\CustomerOrderController::class, 'cancel'])
        ->name('cancel');
    Route::get('{orderNumber}/tracking', [\App\Modules\Shipping\Controllers\CustomerTrackingController::class, 'trackOrder'])
        ->name('tracking');
});

// ─────────────────────────────────────────────────────────────────────────────
// Orders — Admin Order Management & Fulfilment Lifecycle
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('admin/orders')
    ->name('admin.orders.')
    ->middleware(['auth:sanctum', 'staff'])
    ->group(function () {
        Route::get('/', [\App\Modules\Orders\Controllers\AdminOrderController::class, 'index'])
            ->name('index');
        Route::get('{order}', [\App\Modules\Orders\Controllers\AdminOrderController::class, 'show'])
            ->name('show');
        Route::post('{order}/confirm', [\App\Modules\Orders\Controllers\AdminOrderController::class, 'confirm'])
            ->name('confirm');
        Route::post('{order}/process', [\App\Modules\Orders\Controllers\AdminOrderController::class, 'process'])
            ->name('process');
        Route::post('{order}/ship', [\App\Modules\Orders\Controllers\AdminOrderController::class, 'ship'])
            ->name('ship');
        Route::post('{order}/deliver', [\App\Modules\Orders\Controllers\AdminOrderController::class, 'deliver'])
            ->name('deliver');
        Route::post('{order}/cancel', [\App\Modules\Orders\Controllers\AdminOrderController::class, 'cancel'])
            ->name('cancel');
        Route::post('{order}/refund', [\App\Modules\Orders\Controllers\AdminOrderController::class, 'refund'])
            ->name('refund');
    });

// ─────────────────────────────────────────────────────────────────────────────
// Payments — Initiation, Server-Side Verification, Callbacks & Webhooks
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('payments')->name('payments.')->group(function () {
    Route::post('initiate', [\App\Modules\Payments\Controllers\PaymentController::class, 'initiate'])
        ->middleware('throttle:20,1')
        ->name('initiate');
    Route::post('verify', [\App\Modules\Payments\Controllers\PaymentController::class, 'verify'])
        ->middleware('throttle:30,1')
        ->name('verify');
    Route::match(['get', 'post'], 'callback/{gateway}', [\App\Modules\Payments\Controllers\PaymentController::class, 'callback'])
        ->middleware('throttle:60,1')
        ->name('callback');
});

// ─────────────────────────────────────────────────────────────────────────────
// Shipping — Admin Consignment Management, Tracking & COD Reconciliation
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('admin/shipments')
    ->name('admin.shipments.')
    ->middleware(['auth:sanctum', 'staff'])
    ->group(function () {
        Route::get('/', [\App\Modules\Shipping\Controllers\AdminShipmentController::class, 'index'])
            ->name('index');
        Route::post('/', [\App\Modules\Shipping\Controllers\AdminShipmentController::class, 'store'])
            ->name('store');
        Route::post('calculate-charge', [\App\Modules\Shipping\Controllers\AdminShipmentController::class, 'calculateCharge'])
            ->name('calculate-charge');
        Route::get('cod-reconciliation', [\App\Modules\Shipping\Controllers\AdminShipmentController::class, 'codReconciliationReport'])
            ->name('cod-reconciliation.report');
        Route::post('cod-reconciliation', [\App\Modules\Shipping\Controllers\AdminShipmentController::class, 'reconcileCod'])
            ->name('cod-reconciliation.store');
        Route::get('{shipment}', [\App\Modules\Shipping\Controllers\AdminShipmentController::class, 'show'])
            ->name('show');
        Route::post('{shipment}/cancel', [\App\Modules\Shipping\Controllers\AdminShipmentController::class, 'cancel'])
            ->name('cancel');
        Route::post('{shipment}/track', [\App\Modules\Shipping\Controllers\AdminShipmentController::class, 'track'])
            ->name('track');
    });

// ─────────────────────────────────────────────────────────────────────────────
// Payments & Courier Webhooks
// ─────────────────────────────────────────────────────────────────────────────
Route::post('webhooks/shipping/{courier}', [\App\Modules\Shipping\Controllers\CustomerTrackingController::class, 'webhook'])
    ->middleware('throttle:300,1')
    ->name('webhooks.shipping');

// Gateways call this from a small set of IPs and may retry in bursts, so the limit is generous.
Route::post('webhooks/{gateway}', [\App\Modules\Payments\Controllers\PaymentController::class, 'webhook'])
    ->middleware('throttle:300,1')
    ->name('webhooks.payment');

