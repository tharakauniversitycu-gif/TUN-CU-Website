<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => 'sanctum',
        'passwords' => 'members',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Two separate Sanctum-based guards are defined:
    |
    |  - "sanctum"        → for Member portal authentication
    |  - "admin-sanctum"  → for Admin dashboard authentication
    |
    | Both use Sanctum's token driver but point to different
    | Eloquent providers, so a member token can never authenticate
    | as an admin and vice-versa.
    |
    */

    'guards' => [
        'sanctum' => [
            'driver'   => 'sanctum',
            'provider' => 'members',
        ],

        'admin-sanctum' => [
            'driver'   => 'sanctum',
            'provider' => 'admins',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'members' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Member::class,
        ],

        'admins' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Admin::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'members' => [
            'provider' => 'members',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
