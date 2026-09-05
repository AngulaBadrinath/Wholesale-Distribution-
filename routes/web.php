<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\SessionManagementController;
use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public routes
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'phpVersion' => PHP_VERSION,
        'laravelVersion' => app()->version(),
    ]);
})->name('home');

Route::get('/health', HealthCheckController::class)->name('health');

// Guest authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/login/mfa', [\App\Http\Controllers\Auth\MfaChallengeController::class, 'create'])->name('mfa.challenge');
    Route::post('/login/mfa', [\App\Http\Controllers\Auth\MfaChallengeController::class, 'store'])->name('mfa.challenge.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

// Authenticated routes with active account enforcement
Route::middleware(['auth', 'account.active'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', function () {
        return Inertia::render('Welcome', [
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
        ]);
    })->name('dashboard');

    // Active session tracking and revocation
    Route::get('/security/sessions', [SessionManagementController::class, 'index'])->name('sessions.index');
    Route::post('/security/sessions/{id}/revoke', [SessionManagementController::class, 'destroy'])->name('sessions.revoke');
    Route::post('/security/sessions/revoke-others', [SessionManagementController::class, 'destroyOthers'])->name('sessions.revoke-others');
    Route::post('/security/sessions/revoke-all', [SessionManagementController::class, 'destroyAll'])->name('sessions.revoke-all');

    // Multi-factor authentication settings
    Route::get('/security/mfa', [\App\Http\Controllers\Security\TwoFactorAuthenticationController::class, 'index'])->name('mfa.index');
    Route::post('/security/mfa/enable', [\App\Http\Controllers\Security\TwoFactorAuthenticationController::class, 'enable'])->name('mfa.enable');
    Route::post('/security/mfa/confirm', [\App\Http\Controllers\Security\TwoFactorAuthenticationController::class, 'confirm'])->name('mfa.confirm');
    Route::delete('/security/mfa', [\App\Http\Controllers\Security\TwoFactorAuthenticationController::class, 'disable'])->name('mfa.disable');
    Route::post('/security/mfa/recovery-codes', [\App\Http\Controllers\Security\TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])->name('mfa.recovery-codes');

    // System & Role management (requires role.manage permission)
    Route::middleware('permission:role.manage')->group(function () {
        Route::get('/security/roles', [\App\Http\Controllers\Security\RoleAssignmentController::class, 'index'])->name('roles.index');
        Route::put('/security/users/{user}/role', [\App\Http\Controllers\Security\RoleAssignmentController::class, 'update'])->name('users.role.update');

        // Company Information Settings
        Route::get('/system/company', [\App\Http\Controllers\System\CompanyInformationController::class, 'index'])->name('system.company.index');
        Route::put('/system/company', [\App\Http\Controllers\System\CompanyInformationController::class, 'update'])->name('system.company.update');
    });

    // Customer Management
    Route::middleware('permission:customer.view')->group(function () {
        Route::get('/customers', [\App\Http\Controllers\Customer\CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [\App\Http\Controllers\Customer\CustomerController::class, 'show'])->name('customers.show');
    });

    Route::middleware('permission:customer.create')->group(function () {
        Route::get('/customers-create', [\App\Http\Controllers\Customer\CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [\App\Http\Controllers\Customer\CustomerController::class, 'store'])->name('customers.store');
    });

    Route::middleware('permission:customer.update')->group(function () {
        Route::get('/customers/{customer}/edit', [\App\Http\Controllers\Customer\CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [\App\Http\Controllers\Customer\CustomerController::class, 'update'])->name('customers.update');
        Route::patch('/customers/{customer}/status', [\App\Http\Controllers\Customer\CustomerController::class, 'updateStatus'])->name('customers.status');
        Route::patch('/customers/{customer}/assign', [\App\Http\Controllers\Customer\CustomerController::class, 'assignSalesman'])->name('customers.assign');
    });

    // Salesman Management
    Route::middleware('permission:user.view')->group(function () {
        Route::get('/salesmen', [\App\Http\Controllers\Salesman\SalesmanController::class, 'index'])->name('salesmen.index');
        Route::get('/salesmen/{salesman}', [\App\Http\Controllers\Salesman\SalesmanController::class, 'show'])->name('salesmen.show');
    });

    Route::middleware('permission:user.create')->group(function () {
        Route::get('/salesmen-create', [\App\Http\Controllers\Salesman\SalesmanController::class, 'create'])->name('salesmen.create');
        Route::post('/salesmen', [\App\Http\Controllers\Salesman\SalesmanController::class, 'store'])->name('salesmen.store');
    });

    Route::middleware('permission:user.update')->group(function () {
        Route::get('/salesmen/{salesman}/edit', [\App\Http\Controllers\Salesman\SalesmanController::class, 'edit'])->name('salesmen.edit');
        Route::put('/salesmen/{salesman}', [\App\Http\Controllers\Salesman\SalesmanController::class, 'update'])->name('salesmen.update');
        Route::patch('/salesmen/{salesman}/status', [\App\Http\Controllers\Salesman\SalesmanController::class, 'updateStatus'])->name('salesmen.status');
    });

    // Product Management
    Route::middleware('permission:product.view')->group(function () {
        Route::get('/products', [\App\Http\Controllers\Product\ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [\App\Http\Controllers\Product\ProductController::class, 'show'])->name('products.show');

        // Category Viewing
        Route::get('/categories', [\App\Http\Controllers\Category\CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/{category}', [\App\Http\Controllers\Category\CategoryController::class, 'show'])->whereNumber('category')->name('categories.show');
    });

    Route::middleware('permission:product.create')->group(function () {
        Route::get('/products-create', [\App\Http\Controllers\Product\ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [\App\Http\Controllers\Product\ProductController::class, 'store'])->name('products.store');

        // Category Creation
        Route::get('/categories/create', [\App\Http\Controllers\Category\CategoryController::class, 'create'])->name('categories.create');
        Route::get('/categories-create', [\App\Http\Controllers\Category\CategoryController::class, 'create']);
        Route::post('/categories', [\App\Http\Controllers\Category\CategoryController::class, 'store'])->name('categories.store');
    });

    Route::middleware('permission:product.update')->group(function () {
        Route::get('/products/{product}/edit', [\App\Http\Controllers\Product\ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [\App\Http\Controllers\Product\ProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/status', [\App\Http\Controllers\Product\ProductController::class, 'updateStatus'])->name('products.status');

        // Product Image Management
        Route::post('/products/{product}/images', [\App\Http\Controllers\Product\ProductImageController::class, 'store'])->name('products.images.store');
        Route::patch('/products/{product}/images/{image}/primary', [\App\Http\Controllers\Product\ProductImageController::class, 'setPrimary'])->name('products.images.primary');
        Route::delete('/products/{product}/images/{image}', [\App\Http\Controllers\Product\ProductImageController::class, 'destroy'])->name('products.images.destroy');

        // Category Management & Lifecycle
        Route::get('/categories/{category}/edit', [\App\Http\Controllers\Category\CategoryController::class, 'edit'])->whereNumber('category')->name('categories.edit');
        Route::put('/categories/{category}', [\App\Http\Controllers\Category\CategoryController::class, 'update'])->whereNumber('category')->name('categories.update');
        Route::match(['put', 'patch'], '/categories/{category}/status', [\App\Http\Controllers\Category\CategoryController::class, 'updateStatus'])->whereNumber('category')->name('categories.status');
        Route::delete('/categories/{category}', [\App\Http\Controllers\Category\CategoryController::class, 'destroy'])->whereNumber('category')->name('categories.destroy');
    });

    // Tax Profile Management
    Route::middleware('permission:product.tax.update')->group(function () {
        Route::get('/tax-profiles', [\App\Http\Controllers\Tax\TaxProfileController::class, 'index'])->name('tax-profiles.index');
        Route::get('/tax-profiles/create', [\App\Http\Controllers\Tax\TaxProfileController::class, 'create'])->name('tax-profiles.create');
        Route::get('/tax-profiles-create', [\App\Http\Controllers\Tax\TaxProfileController::class, 'create']);
        Route::post('/tax-profiles', [\App\Http\Controllers\Tax\TaxProfileController::class, 'store'])->name('tax-profiles.store');
        Route::get('/tax-profiles/{tax_profile}/edit', [\App\Http\Controllers\Tax\TaxProfileController::class, 'edit'])->whereNumber('tax_profile')->name('tax-profiles.edit');
        Route::put('/tax-profiles/{tax_profile}', [\App\Http\Controllers\Tax\TaxProfileController::class, 'update'])->whereNumber('tax_profile')->name('tax-profiles.update');
        Route::delete('/tax-profiles/{tax_profile}', [\App\Http\Controllers\Tax\TaxProfileController::class, 'destroy'])->whereNumber('tax_profile')->name('tax-profiles.destroy');
    });

    // Salesman Ordering & Drafts
    Route::middleware('permission:order.create')->group(function () {
        Route::get('/salesman/orders/create', [\App\Http\Controllers\Salesman\SalesmanOrderController::class, 'create'])->name('salesman.orders.create');
        Route::post('/salesman/orders', [\App\Http\Controllers\Salesman\SalesmanOrderController::class, 'store'])->name('salesman.orders.store');

        // Draft Management
        Route::get('/salesman/orders/drafts', [\App\Http\Controllers\Salesman\SalesmanOrderController::class, 'drafts'])->name('salesman.orders.drafts');
        Route::post('/salesman/orders/drafts', [\App\Http\Controllers\Salesman\SalesmanOrderController::class, 'saveDraft'])->name('salesman.orders.drafts.store');
        Route::put('/salesman/orders/drafts/{order}', [\App\Http\Controllers\Salesman\SalesmanOrderController::class, 'saveDraft'])->whereNumber('order')->name('salesman.orders.drafts.update');
        Route::get('/salesman/orders/drafts/{order}/edit', [\App\Http\Controllers\Salesman\SalesmanOrderController::class, 'editDraft'])->whereNumber('order')->name('salesman.orders.drafts.edit');
        Route::post('/salesman/orders/drafts/{order}/submit', [\App\Http\Controllers\Salesman\SalesmanOrderController::class, 'submitDraft'])->whereNumber('order')->name('salesman.orders.drafts.submit');
        Route::delete('/salesman/orders/drafts/{order}', [\App\Http\Controllers\Salesman\SalesmanOrderController::class, 'discardDraft'])->whereNumber('order')->name('salesman.orders.drafts.destroy');
    });

    Route::middleware('permission:order.view')->group(function () {
        // Salesman Orders
        Route::get('/salesman/orders', [\App\Http\Controllers\Salesman\SalesmanOrderController::class, 'index'])->name('salesman.orders.index');
        Route::get('/salesman/orders/{order}', [\App\Http\Controllers\Salesman\SalesmanOrderController::class, 'show'])->whereNumber('order')->name('salesman.orders.show');

        // Admin Operational Order Queues & Review Workspace
        Route::get('/admin/orders', [\App\Http\Controllers\Admin\AdminOrderController::class, 'index'])->name('admin.orders.index');
        Route::get('/admin/orders/{order}', [\App\Http\Controllers\Admin\AdminOrderController::class, 'show'])->whereNumber('order')->name('admin.orders.show');
        Route::get('/admin/orders/{order}/review', [\App\Http\Controllers\Admin\AdminOrderController::class, 'review'])->whereNumber('order')->name('admin.orders.review');
    });
});


