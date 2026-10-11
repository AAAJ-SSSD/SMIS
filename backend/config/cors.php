<?php

/*
| Strict CORS: only the SPA at FRONTEND_URL may call the API, with credentials
| (session cookie). No wildcards.
*/

return [

    'paths' => ['api/*', 'auth/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN', 'Accept-Language'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => true,

];
