<?php

use App\Models\User;
use App\Support\DevLogin;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class))
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const SCHOOL_DOMAIN = 'school.edu';

function userWithRole(string $role, array $attributes = []): User
{
    return User::factory()->withRole($role)->create($attributes);
}

/**
 * Turn Google login on with a fully valid configuration.
 */
function enableGoogleLogin(string $domain = SCHOOL_DOMAIN): void
{
    config([
        'school.google.enabled' => true,
        'school.google.domain' => $domain,
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
        'services.google.redirect' => 'http://localhost:8000/auth/google/callback',
    ]);
}

/**
 * Make Socialite return the given Google account on callback.
 */
function fakeGoogleUser(string $email, string $sub = 'google-sub-123', ?string $hd = SCHOOL_DOMAIN, bool $verified = true): void
{
    $raw = array_filter([
        'sub' => $sub,
        'email' => $email,
        'email_verified' => $verified,
        'hd' => $hd,
        'name' => 'Google Person',
        'picture' => 'https://lh3.googleusercontent.com/a/photo',
    ], fn ($value) => $value !== null);

    $googleUser = (new SocialiteUser)->setRaw($raw)->map([
        'id' => $sub,
        'name' => 'Google Person',
        'email' => $email,
        'avatar' => 'https://lh3.googleusercontent.com/a/photo',
    ]);

    Socialite::shouldReceive('driver->user')->andReturn($googleUser);
}

/**
 * Simulate booting in a given environment with DEV_LOGIN_ENABLED=true:
 * routes/web.php calls DevLogin::registerRoutes() exactly like this at boot.
 *
 * Outside the "testing" environment Laravel enforces CSRF in tests too,
 * so a real token is put in the session and sent as a header.
 */
function bootDevLogin(string $environment): void
{
    app()->detectEnvironment(fn () => $environment);
    config(['school.dev_login_enabled' => true]);

    Route::middleware('web')->group(fn () => DevLogin::registerRoutes());
    app('router')->getRoutes()->refreshNameLookups();

    test()->withSession(['_token' => 'csrf-test-token'])->withHeader('X-CSRF-TOKEN', 'csrf-test-token');
}

function lastLoginAudit(): ?Activity
{
    return Activity::where('log_name', 'auth')->latest('id')->first();
}
