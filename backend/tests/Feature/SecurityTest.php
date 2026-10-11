<?php

use App\Support\Access;

it('sends security headers on every response', function () {
    $this->getJson('/auth/config')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('sends HSTS only in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->getJson('/auth/config')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('allows CORS only for FRONTEND_URL, with credentials', function () {
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'GET'])
        ->options('/api/me')
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');

    // With a single allowed origin the header always names FRONTEND_URL, so the
    // browser rejects any other origin; it must never echo the caller's origin.
    $evil = $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'GET'])
        ->options('/api/me');

    expect($evil->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example')
        ->and($evil->headers->get('Access-Control-Allow-Origin'))->not->toBe('*');
});

it('only treats the SPA origin as stateful for Sanctum', function () {
    expect(config('sanctum.stateful'))->toBe(['localhost:5173']);
});

it('uses httpOnly, SameSite=Lax session cookies', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        ->and(config('session.driver'))->toBe('database');
});

it('never exposes internal or secret user fields', function () {
    $admin = userWithRole(Access::ADMIN);
    $target = userWithRole(Access::TEACHER);
    $target->forceFill(['google_sub' => 'sub', 'password' => 'x', 'two_factor_secret' => 'y'])->save();

    $json = $this->actingAs($admin)->getJson("/api/users/{$target->ulid}")->json('data');

    expect($json)->not->toHaveKeys(['ulid', 'google_sub', 'password', 'two_factor_secret', 'remember_token', 'failed_login_attempts'])
        ->and($json['id'])->toBe($target->ulid);
});
