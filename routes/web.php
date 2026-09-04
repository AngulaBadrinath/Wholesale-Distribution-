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
});
