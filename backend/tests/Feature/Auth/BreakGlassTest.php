<?php

use App\Support\Access;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;

const BG_EMAIL = 'root@school.edu';
const BG_PASSWORD = 'correct-horse-battery-staple';

beforeEach(function () {
    config(['school.super_admin_email' => BG_EMAIL]);

    $this->secret = app(Google2FA::class)->generateSecretKey(32);
    $this->admin = userWithRole(Access::SUPER_ADMIN, ['email' => BG_EMAIL]);
    $this->admin->forceFill(['password' => BG_PASSWORD, 'two_factor_secret' => $this->secret])->save();
});

function totp(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

it('logs in with password and a valid TOTP code', function () {
    $this->postJson('/auth/break-glass', ['email' => BG_EMAIL, 'password' => BG_PASSWORD, 'code' => totp($this->secret)])
        ->assertOk()
        ->assertJsonPath('data.id', $this->admin->ulid);

    $this->assertAuthenticatedAs($this->admin);
    expect(lastLoginAudit())->event->toBe('login_succeeded')
        ->and(lastLoginAudit()->properties['method'])->toBe('break_glass');
});

it('rejects a wrong TOTP code', function () {
    $this->postJson('/auth/break-glass', ['email' => BG_EMAIL, 'password' => BG_PASSWORD, 'code' => '000000'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_credentials');

    $this->assertGuest();
});

it('rejects a replayed TOTP code', function () {
    $code = totp($this->secret);

    $this->postJson('/auth/break-glass', ['email' => BG_EMAIL, 'password' => BG_PASSWORD, 'code' => $code])->assertOk();
    $this->post('/auth/logout');

    $this->postJson('/auth/break-glass', ['email' => BG_EMAIL, 'password' => BG_PASSWORD, 'code' => $code])->assertStatus(422);
});

it('locks the account after 5 failed attempts', function () {
    foreach (range(1, 4) as $_) {
        $this->postJson('/auth/break-glass', ['email' => BG_EMAIL, 'password' => 'wrong', 'code' => '123456'])
            ->assertStatus(422);
    }

    // The 5th failure triggers the lock.
    $this->postJson('/auth/break-glass', ['email' => BG_EMAIL, 'password' => 'wrong', 'code' => '123456'])
        ->assertStatus(423)
        ->assertJsonPath('code', 'locked');

    // Even correct credentials are now refused.
    $this->postJson('/auth/break-glass', ['email' => BG_EMAIL, 'password' => BG_PASSWORD, 'code' => totp($this->secret)])
        ->assertStatus(423);

    $this->assertGuest();
    expect($this->admin->fresh()->isLocked())->toBeTrue();
    expect(Activity::where('log_name', 'auth')->where('event', 'login_failed')->count())->toBe(6);
});

it('unlocks after the lock period', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/auth/break-glass', ['email' => BG_EMAIL, 'password' => 'wrong', 'code' => '123456']);
    }

    $this->travel(config('school.break_glass.lock_minutes') + 1)->minutes();

    $this->postJson('/auth/break-glass', ['email' => BG_EMAIL, 'password' => BG_PASSWORD, 'code' => totp($this->secret)])
        ->assertOk();
});

it('only works for the SUPER_ADMIN_EMAIL account', function () {
    $other = userWithRole(Access::SUPER_ADMIN, ['email' => 'other@school.edu']);
    $other->forceFill(['password' => BG_PASSWORD, 'two_factor_secret' => $this->secret])->save();

    $this->postJson('/auth/break-glass', ['email' => 'other@school.edu', 'password' => BG_PASSWORD, 'code' => totp($this->secret)])
        ->assertStatus(422);

    $this->assertGuest();
});

it('refuses when the account has no break-glass credentials configured', function () {
    $this->admin->forceFill(['password' => null, 'two_factor_secret' => null])->save();

    $this->postJson('/auth/break-glass', ['email' => BG_EMAIL, 'password' => BG_PASSWORD, 'code' => '123456'])
        ->assertStatus(422);
});

it('is rate-limited per IP', function () {
    foreach (range(1, 10) as $_) {
        $this->postJson('/auth/break-glass', ['email' => 'nobody@school.edu', 'password' => 'x', 'code' => '123456']);
    }

    $this->postJson('/auth/break-glass', ['email' => 'nobody@school.edu', 'password' => 'x', 'code' => '123456'])
        ->assertTooManyRequests();
});

it('never stores the secrets in plain text', function () {
    $row = DB::table('users')->where('id', $this->admin->id)->first();

    expect($row->two_factor_secret)->not->toBe($this->secret)
        ->and($row->password)->not->toBe(BG_PASSWORD);
});
