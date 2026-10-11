<?php

use App\Support\GoogleLogin;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Facade;

/**
 * Boot a brand-new application with the given environment variables and
 * return the exception it threw (or null if it booted).
 */
function bootWithEnv(array $vars): ?Throwable
{
    $previous = [];
    foreach ($vars as $key => $value) {
        $previous[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
        $_ENV[$key] = $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }

    $testApp = app();

    try {
        $app = require __DIR__.'/../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return null;
    } catch (Throwable $e) {
        return $e;
    } finally {
        // Hand the container and facades back to the test's application.
        Container::setInstance($testApp);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($testApp);

        foreach ($previous as $key => [$env, $server, $putenv]) {
            $env === null ? Arr::forget($_ENV, $key) : $_ENV[$key] = $env;
            $server === null ? Arr::forget($_SERVER, $key) : $_SERVER[$key] = $server;
            $putenv === false ? putenv($key) : putenv("{$key}={$putenv}");
        }
    }
}

it('refuses to start when Google login is on and SCHOOL_GOOGLE_DOMAIN is empty', function () {
    $error = bootWithEnv([
        'GOOGLE_LOGIN_ENABLED' => 'true',
        'GOOGLE_CLIENT_ID' => 'id',
        'GOOGLE_CLIENT_SECRET' => 'secret',
        'SCHOOL_GOOGLE_DOMAIN' => '',
    ]);

    expect($error)->toBeInstanceOf(RuntimeException::class)
        ->and($error->getMessage())->toContain('SCHOOL_GOOGLE_DOMAIN');
});

it('starts when Google login is on and the domain is set', function () {
    expect(bootWithEnv([
        'GOOGLE_LOGIN_ENABLED' => 'true',
        'GOOGLE_CLIENT_ID' => 'id',
        'GOOGLE_CLIENT_SECRET' => 'secret',
        'SCHOOL_GOOGLE_DOMAIN' => SCHOOL_DOMAIN,
    ]))->toBeNull();
});

it('starts when Google login is off, even without a domain', function () {
    expect(bootWithEnv(['GOOGLE_LOGIN_ENABLED' => 'false', 'SCHOOL_GOOGLE_DOMAIN' => '']))->toBeNull();
});

it('is only active when enabled and fully configured', function () {
    enableGoogleLogin();
    expect(GoogleLogin::isActive())->toBeTrue();

    config(['services.google.client_secret' => null]);
    expect(GoogleLogin::isActive())->toBeFalse();

    enableGoogleLogin();
    config(['school.google.enabled' => false]);
    expect(GoogleLogin::isActive())->toBeFalse();
});

it('hides the Google button when Google login is off', function () {
    $this->getJson('/auth/config')->assertExactJson(['google_enabled' => false, 'dev_login_enabled' => false, 'school_domain' => null]);

    enableGoogleLogin();
    $this->getJson('/auth/config')->assertJsonPath('google_enabled', true);
});

it('matches the email domain exactly', function (string $email, bool $expected) {
    enableGoogleLogin();

    expect(GoogleLogin::emailMatchesDomain($email))->toBe($expected);
})->with([
    ['teacher@school.edu', true],
    ['Teacher@SCHOOL.EDU', true],
    ['teacher@gmail.com', false],
    ['teacher@evil-school.edu', false],
    ['teacher@sub.school.edu', false],
    ['teacher@school.edu.evil.com', false],
    ['a@b@school.edu', false],
]);
