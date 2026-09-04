<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
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
});
