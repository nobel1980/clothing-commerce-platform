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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Implicitly grant 'super_admin' role all permission checks
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        // Register domain policies
        Gate::policy(\App\Modules\Identity\Models\User::class, \App\Modules\Identity\Policies\UserPolicy::class);
        Gate::policy(\App\Modules\Identity\Models\Address::class, \App\Modules\Identity\Policies\AddressPolicy::class);
        Gate::policy(\Spatie\Permission\Models\Role::class, \App\Modules\Identity\Policies\RolePolicy::class);
    }
}

