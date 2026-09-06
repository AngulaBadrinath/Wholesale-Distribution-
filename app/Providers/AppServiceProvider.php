<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\InventoryBalance;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\StockException;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DeliveryPolicy;
use App\Policies\InventoryBalancePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OrderAdjustmentPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ReturnRequestPolicy;
use App\Policies\StockExceptionPolicy;
use App\Policies\UserPolicy;
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
        // Register Model -> Policy mappings
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(OrderAdjustment::class, OrderAdjustmentPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Delivery::class, DeliveryPolicy::class);
        Gate::policy(ReturnRequest::class, ReturnRequestPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(InventoryBalance::class, InventoryBalancePolicy::class);
        Gate::policy(StockException::class, StockExceptionPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);

        // Register authoritative Gate::before resolver for canonical Permission enum values
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            $permission = Permission::tryFrom($ability);

            if ($permission !== null) {
                $hasPermission = app(PermissionService::class)->has($user, $permission);

                if (! $hasPermission) {
                    return false; // Fail closed if user lacks baseline permission
                }

                // When arguments (models / resources) are provided, return null to delegate
                // to the registered model policy for fine-grained resource scope evaluation.
                if (! empty($arguments)) {
                    return null;
                }

                return true;
            }

            return null;
        });
    }
}
