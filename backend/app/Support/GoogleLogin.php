<?php

namespace App\Support;

use RuntimeException;

class GoogleLogin
{
    /**
     * Refuse to boot when Google login is switched on without a school domain:
     * without it any Google account on the internet would pass the domain check.
     *
     * @throws RuntimeException
     */
    public static function assertConfigured(): void
    {
        if (config('school.google.enabled') && static::domain() === '') {
            throw new RuntimeException(
                'GOOGLE_LOGIN_ENABLED=true but SCHOOL_GOOGLE_DOMAIN is empty. '
                .'Set SCHOOL_GOOGLE_DOMAIN to your school Google Workspace domain or disable Google login.'
            );
        }
    }

    /**
     * Google sign-in is offered only when it is switched on and fully configured.
     */
    public static function isActive(): bool
    {
        return config('school.google.enabled')
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && static::domain() !== '';
    }

    public static function domain(): string
    {
        return (string) config('school.google.domain');
    }

    /**
     * Whether an email address belongs exactly to the school domain (no subdomains).
     */
    public static function emailMatchesDomain(?string $email): bool
    {
        $domain = static::domain();

        if ($domain === '' || ! is_string($email) || substr_count($email, '@') !== 1) {
            return false;
        }

        return strtolower(substr(strrchr($email, '@'), 1)) === $domain;
    }
}
