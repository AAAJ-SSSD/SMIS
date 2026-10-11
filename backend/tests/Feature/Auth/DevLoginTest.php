<?php

use App\Models\User;
use App\Support\Access;
use App\Support\DevLogin;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\Route;

it('does not exist in the testing environment even with the flag on', function () {
    config(['school.dev_login_enabled' => true]);

    $this->getJson('/auth/dev/users')->assertNotFound();
    $this->postJson('/auth/dev/login', ['ulid' => (string) str()->ulid()])->assertNotFound();
});

it('returns 404 when APP_ENV is not local, even with DEV_LOGIN_ENABLED=true', function (string $environment) {
    bootDevLogin($environment);

    expect(DevLogin::enabled())->toBeFalse();
    $this->getJson('/auth/dev/users')->assertNotFound();
    $this->postJson('/auth/dev/login', ['ulid' => (string) str()->ulid()])->assertNotFound();
})->with(['production', 'staging', 'testing']);

it('returns 404 in local when DEV_LOGIN_ENABLED is false', function () {
    app()->detectEnvironment(fn () => 'local');
    config(['school.dev_login_enabled' => false]);
    Route::middleware('web')->group(fn () => DevLogin::registerRoutes());

    $this->getJson('/auth/dev/users')->assertNotFound();
});

it('lists seeded test users and logs in through a normal session in local', function () {
    bootDevLogin('local');
    $this->seed(TestUsersSeeder::class);
    $teacher = User::where('email', 'teacher@example.test')->firstOrFail();

    $this->getJson('/auth/dev/users')
        ->assertOk()
        ->assertJsonFragment(['email' => 'teacher@example.test']);

    $this->postJson('/auth/dev/login', ['ulid' => $teacher->ulid])
        ->assertOk()
        ->assertJsonPath('data.id', $teacher->ulid);

    $this->assertAuthenticatedAs($teacher);
    expect($this->getJson('/auth/config')->json('dev_login_enabled'))->toBeTrue();
});

it('enforces CSRF on the dev login', function () {
    bootDevLogin('local');
    $this->seed(TestUsersSeeder::class);

    $this->withHeader('X-CSRF-TOKEN', 'wrong-token')
        ->postJson('/auth/dev/login', ['ulid' => User::where('email', 'admin@example.test')->value('ulid')])
        ->assertStatus(419);

    $this->assertGuest();
});

it('cannot log in as a real (non example.test) account', function () {
    bootDevLogin('local');
    $real = userWithRole(Access::SUPER_ADMIN, ['email' => 'head@'.SCHOOL_DOMAIN]);

    $this->postJson('/auth/dev/login', ['ulid' => $real->ulid])->assertStatus(422);

    $this->assertGuest();
});

it('refuses inactive test users', function () {
    bootDevLogin('local');
    $this->seed(TestUsersSeeder::class);
    $inactive = User::where('email', 'inactive@example.test')->firstOrFail();

    $this->postJson('/auth/dev/login', ['ulid' => $inactive->ulid])->assertForbidden();

    $this->assertGuest();
});

it('refuses to seed fake users outside local', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(TestUsersSeeder::class)->run())->toThrow(RuntimeException::class);
    expect(User::where('email', 'like', '%@example.test')->count())->toBe(0);
});
