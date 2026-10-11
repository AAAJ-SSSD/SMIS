<?php

namespace App\Support;

use App\Http\Controllers\Auth\DevLoginController;
use Illuminate\Support\Facades\Route;

class DevLogin
{
    /**
     * Dev login exists only when BOTH APP_ENV=local and DEV_LOGIN_ENABLED=true.
     */
    public static function enabled(): bool
    {
        return app()->environment('local') && config('school.dev_login_enabled') === true;
    }

    /**
     * Register the dev login routes (called from routes/web.php, inside the web group).
     * Outside of local they are never registered, so they 404 rather than merely refusing.
     */
    public static function registerRoutes(): void
    {
        if (! static::enabled()) {
            return;
        }

        Route::middleware('throttle:login')->prefix('auth/dev')->group(function () {
            Route::get('users', [DevLoginController::class, 'index'])->name('auth.dev.users');
            Route::post('login', [DevLoginController::class, 'store'])->name('auth.dev.login');
        });
    }
}
