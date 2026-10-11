<?php

namespace App\Providers;

use App\Policies\ActivityPolicy;
use App\Policies\RolePolicy;
use App\Support\GoogleLogin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

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
        // Fail fast: never run with Google login on but no domain restriction.
        GoogleLogin::assertConfigured();

        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by('login|'.$request->ip()));

        // Per-IP limit on top of the per-account lockout (5 failures) in BreakGlassController.
        RateLimiter::for('break-glass', fn (Request $request) => Limit::perMinute(10)->by('break-glass|'.$request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->getKey() ?: $request->ip()));
    }
}
