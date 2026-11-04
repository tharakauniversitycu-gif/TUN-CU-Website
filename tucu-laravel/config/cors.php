<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Add the URL(s) where your frontend (public/index.html, member.html,
    | admin.html) is hosted. For local development with a simple static
    | server, http://localhost and http://127.0.0.1 (any port) are common.
    |
    */
    'allowed_origins' => [
        'http://localhost',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'http://localhost:3000',
        'http://localhost:5500',
        'http://127.0.0.1:5500',
        // Add your production frontend domain here, e.g.:
        // 'https://tucu.ac.ke',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];

