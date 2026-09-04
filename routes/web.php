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
    });

    Route::middleware('permission:product.create')->group(function () {
        Route::get('/products-create', [\App\Http\Controllers\Product\ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [\App\Http\Controllers\Product\ProductController::class, 'store'])->name('products.store');
    });

    Route::middleware('permission:product.update')->group(function () {
        Route::get('/products/{product}/edit', [\App\Http\Controllers\Product\ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [\App\Http\Controllers\Product\ProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/status', [\App\Http\Controllers\Product\ProductController::class, 'updateStatus'])->name('products.status');
    });
});

