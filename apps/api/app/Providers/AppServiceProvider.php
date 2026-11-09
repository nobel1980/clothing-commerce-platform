<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Shared\Contracts\Inventory\InventoryServiceContract::class,
            \App\Modules\Inventory\Services\InventoryService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\Inventory\Commands\ExpireReservationsCommand::class,
            ]);
        }

        // Implicitly grant 'super_admin' role all permission checks
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        // Register domain policies
        Gate::policy(\App\Modules\Identity\Models\User::class, \App\Modules\Identity\Policies\UserPolicy::class);
        Gate::policy(\App\Modules\Identity\Models\Address::class, \App\Modules\Identity\Policies\AddressPolicy::class);
        Gate::policy(\Spatie\Permission\Models\Role::class, \App\Modules\Identity\Policies\RolePolicy::class);

        // Inventory policies
        Gate::policy(\App\Modules\Inventory\Models\Warehouse::class, \App\Modules\Inventory\Policies\InventoryPolicy::class);
        Gate::policy(\App\Modules\Inventory\Models\InventoryItem::class, \App\Modules\Inventory\Policies\InventoryPolicy::class);
        Gate::policy(\App\Modules\Inventory\Models\StockReservation::class, \App\Modules\Inventory\Policies\InventoryPolicy::class);
    }
}

