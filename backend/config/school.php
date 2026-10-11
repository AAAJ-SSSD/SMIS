<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend
    |--------------------------------------------------------------------------
    |
    | The SPA origin. CORS, Sanctum stateful domains and post-login redirects
    | are all derived from this single value.
    |
    */

    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Google sign-in (school domain only)
    |--------------------------------------------------------------------------
    */

    'google' => [
        'enabled' => (bool) env('GOOGLE_LOGIN_ENABLED', false),
        'domain' => strtolower(trim((string) env('SCHOOL_GOOGLE_DOMAIN', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dev login
    |--------------------------------------------------------------------------
    |
    | Only honoured when APP_ENV=local as well. See App\Support\DevLogin.
    |
    */

    'dev_login_enabled' => (bool) env('DEV_LOGIN_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Break-glass super-admin
    |--------------------------------------------------------------------------
    */

    'super_admin_email' => strtolower(trim((string) env('SUPER_ADMIN_EMAIL', ''))),

    'break_glass' => [
        'max_attempts' => 5,
        'lock_minutes' => (int) env('BREAK_GLASS_LOCK_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    'locales' => ['ar', 'en'],

    'import' => [
        'max_rows' => 1000,
        'max_kilobytes' => 1024,
    ],

];
