<?php

use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'phpVersion' => PHP_VERSION,
        'laravelVersion' => app()->version(),
    ]);
});

Route::get('/health', HealthCheckController::class)->name('health');
