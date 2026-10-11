<?php

use App\Http\Controllers\Auth\BreakGlassController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\SessionController;
use App\Support\DevLogin;
use Illuminate\Support\Facades\Route;

// The backend has no pages of its own; send browsers to the SPA.
Route::get('/', fn () => redirect()->away(config('school.frontend_url')));

Route::prefix('auth')->group(function () {
    Route::get('config', [SessionController::class, 'config'])->name('auth.config');

    Route::middleware('throttle:login')->group(function () {
        Route::get('google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
        Route::get('google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
    });

    Route::post('break-glass', [BreakGlassController::class, 'store'])
        ->middleware('throttle:break-glass')
        ->name('auth.break-glass');

    Route::post('logout', [SessionController::class, 'logout'])
        ->middleware('auth')
        ->name('auth.logout');
});

// Exists only when APP_ENV=local AND DEV_LOGIN_ENABLED=true.
DevLogin::registerRoutes();
