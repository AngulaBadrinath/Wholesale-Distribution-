<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Support\Facades\Gate;
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
        // Register authoritative Gate::before resolver for canonical Permission enum values
        Gate::before(function (User $user, string $ability) {
            $permission = Permission::tryFrom($ability);

            if ($permission !== null) {
                return app(PermissionService::class)->has($user, $permission);
            }

            return null;
        });
    }
}
