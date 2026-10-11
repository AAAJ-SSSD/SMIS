<?php

use App\Models\User;
use App\Support\Access;
use Database\Seeders\TestUsersSeeder;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;

function authAuditCount(): int
{
    return Activity::where('log_name', 'auth')->whereIn('event', ['login_succeeded', 'login_failed'])->count();
}

it('creates exactly one audit entry for every login attempt, of every kind', function () {
    // Google
    enableGoogleLogin();
    userWithRole(Access::TEACHER, ['email' => 'teacher@'.SCHOOL_DOMAIN]);

    $attempts = [
        'google: success' => function () {
            fakeGoogleUser('teacher@'.SCHOOL_DOMAIN);
            $this->get('/auth/google/callback');
        },
        'google: unregistered' => function () {
            fakeGoogleUser('nobody@'.SCHOOL_DOMAIN, sub: 'x1');
            $this->get('/auth/google/callback');
        },
        'google: wrong domain' => function () {
            fakeGoogleUser('teacher@gmail.com', sub: 'x2', hd: null);
            $this->get('/auth/google/callback');
        },
        'break-glass: failure' => fn () => $this->postJson('/auth/break-glass', ['email' => 'root@'.SCHOOL_DOMAIN, 'password' => 'nope', 'code' => '123456']),
    ];

    foreach ($attempts as $label => $attempt) {
        auth()->guard('web')->logout();
        $before = authAuditCount();

        $attempt->call($this);

        expect(authAuditCount())->toBe($before + 1, "No audit entry for [{$label}]");
    }
});

it('audits dev login success and failure', function () {
    bootDevLogin('local');
    $this->seed(TestUsersSeeder::class);

    $before = authAuditCount();
    $this->postJson('/auth/dev/login', ['ulid' => User::where('email', 'admin@example.test')->value('ulid')])->assertOk();
    $this->withSession(['_token' => 'csrf-test-token']); // login rotated the CSRF token
    $this->postJson('/auth/dev/login', ['ulid' => User::where('email', 'inactive@example.test')->value('ulid')])->assertForbidden();

    expect(authAuditCount())->toBe($before + 2);
});

it('audits break-glass success with ip and method', function () {
    config(['school.super_admin_email' => 'root@'.SCHOOL_DOMAIN]);
    $secret = app(Google2FA::class)->generateSecretKey(32);
    userWithRole(Access::SUPER_ADMIN, ['email' => 'root@'.SCHOOL_DOMAIN])
        ->forceFill(['password' => 'a-long-password-123', 'two_factor_secret' => $secret])->save();

    $this->postJson('/auth/break-glass', [
        'email' => 'root@'.SCHOOL_DOMAIN,
        'password' => 'a-long-password-123',
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertOk();

    expect(lastLoginAudit()->properties->only(['method', 'ip'])->all())
        ->toBe(['method' => 'break_glass', 'ip' => '127.0.0.1']);
});

it('never writes the password into the audit log', function () {
    $this->postJson('/auth/break-glass', ['email' => 'root@'.SCHOOL_DOMAIN, 'password' => 'super-secret-value', 'code' => '123456']);

    expect(Activity::all()->toJson())->not->toContain('super-secret-value');
});
