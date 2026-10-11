<?php

use App\Models\User;
use App\Support\Access;

const FRONTEND = 'http://localhost:5173';

beforeEach(fn () => enableGoogleLogin());

it('redirects to Google with a state parameter', function () {
    $response = $this->get('/auth/google/redirect');

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toStartWith('https://accounts.google.com/')
        ->toContain('state=')
        ->toContain('hd='.SCHOOL_DOMAIN);
    $response->assertSessionHas('state');
});

it('returns 404 for Google routes when Google login is off', function () {
    config(['school.google.enabled' => false]);

    $this->get('/auth/google/redirect')->assertNotFound();
    $this->get('/auth/google/callback')->assertNotFound();
});

it('refuses a Google user who is not registered in the system', function () {
    fakeGoogleUser('stranger@'.SCHOOL_DOMAIN);

    $this->get('/auth/google/callback')->assertRedirect(FRONTEND.'/login?error=not_registered');

    $this->assertGuest();
    expect(User::where('email', 'stranger@'.SCHOOL_DOMAIN)->exists())->toBeFalse(); // never auto-created
    expect(lastLoginAudit())
        ->event->toBe('login_failed')
        ->and(lastLoginAudit()->properties['reason'])->toBe('not_registered');
});

it('refuses a gmail.com account even if it is registered', function () {
    userWithRole(Access::TEACHER, ['email' => 'teacher@gmail.com']);
    fakeGoogleUser('teacher@gmail.com', hd: null);

    $this->get('/auth/google/callback')->assertRedirect(FRONTEND.'/login?error=wrong_domain');

    $this->assertGuest();
    expect(lastLoginAudit()->properties['reason'])->toBe('wrong_domain');
});

it('requires both the hd claim and the email domain to match', function (string $email, ?string $hd) {
    userWithRole(Access::TEACHER, ['email' => $email]);
    fakeGoogleUser($email, hd: $hd);

    $this->get('/auth/google/callback')->assertRedirect(FRONTEND.'/login?error=wrong_domain');

    $this->assertGuest();
})->with([
    'school email, no hd claim' => ['teacher@'.SCHOOL_DOMAIN, null],
    'school email, other hd' => ['teacher@'.SCHOOL_DOMAIN, 'other.edu'],
    'gmail email, school hd' => ['teacher@gmail.com', SCHOOL_DOMAIN],
]);

it('refuses an unverified Google email', function () {
    userWithRole(Access::TEACHER, ['email' => 'teacher@'.SCHOOL_DOMAIN]);
    fakeGoogleUser('teacher@'.SCHOOL_DOMAIN, verified: false);

    $this->get('/auth/google/callback')->assertRedirect(FRONTEND.'/login?error=email_not_verified');

    $this->assertGuest();
});

it('signs in a registered, active school user and saves google_sub', function () {
    $user = userWithRole(Access::TEACHER, ['email' => 'teacher@'.SCHOOL_DOMAIN]);
    expect($user->google_sub)->toBeNull();
    fakeGoogleUser('teacher@'.SCHOOL_DOMAIN, sub: 'sub-abc');

    $this->get('/auth/google/callback')->assertRedirect(FRONTEND.'/');

    $this->assertAuthenticatedAs($user);
    $user->refresh();
    expect($user->google_sub)->toBe('sub-abc')
        ->and($user->last_login_at)->not->toBeNull()
        ->and($user->avatar_url)->toBe('https://lh3.googleusercontent.com/a/photo');
    expect(lastLoginAudit())
        ->event->toBe('login_succeeded')
        ->causer_id->toBe($user->id);
});

it('finds a returning user by google_sub', function () {
    $user = userWithRole(Access::TEACHER, ['email' => 'teacher@'.SCHOOL_DOMAIN]);
    $user->forceFill(['google_sub' => 'sub-abc'])->save();
    fakeGoogleUser('teacher@'.SCHOOL_DOMAIN, sub: 'sub-abc');

    $this->get('/auth/google/callback')->assertRedirect(FRONTEND.'/');

    $this->assertAuthenticatedAs($user);
});

it('refuses when the email is bound to a different Google account', function () {
    $user = userWithRole(Access::TEACHER, ['email' => 'teacher@'.SCHOOL_DOMAIN]);
    $user->forceFill(['google_sub' => 'original-sub'])->save();
    fakeGoogleUser('teacher@'.SCHOOL_DOMAIN, sub: 'different-sub');

    $this->get('/auth/google/callback')->assertRedirect(FRONTEND.'/login?error=identity_mismatch');

    $this->assertGuest();
});

it('refuses an inactive user', function () {
    User::factory()->inactive()->withRole(Access::TEACHER)->create(['email' => 'teacher@'.SCHOOL_DOMAIN]);
    fakeGoogleUser('teacher@'.SCHOOL_DOMAIN);

    $this->get('/auth/google/callback')->assertRedirect(FRONTEND.'/login?error=account_inactive');

    $this->assertGuest();
    expect(lastLoginAudit()->properties['reason'])->toBe('account_inactive');
});

it('refuses a soft-deleted user', function () {
    $user = userWithRole(Access::TEACHER, ['email' => 'teacher@'.SCHOOL_DOMAIN]);
    $user->delete();
    fakeGoogleUser('teacher@'.SCHOOL_DOMAIN);

    $this->get('/auth/google/callback')->assertRedirect(FRONTEND.'/login?error=not_registered');

    $this->assertGuest();
});

it('regenerates the session id on login', function () {
    userWithRole(Access::TEACHER, ['email' => 'teacher@'.SCHOOL_DOMAIN]);
    fakeGoogleUser('teacher@'.SCHOOL_DOMAIN);
    $this->startSession();
    $before = session()->getId();

    $this->get('/auth/google/callback');

    expect(session()->getId())->not->toBe($before);
});

it('rate-limits the login routes', function () {
    fakeGoogleUser('stranger@'.SCHOOL_DOMAIN);

    foreach (range(1, 10) as $_) {
        $this->get('/auth/google/callback')->assertRedirect();
    }

    $this->get('/auth/google/callback')->assertTooManyRequests();
});
