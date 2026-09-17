<?php

use App\Models\User;

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
    ],

    'login' => [
        'challenge_lifetime' => (int) env('AUTH_CHALLENGE_LIFETIME', 10),
        'max_otp_attempts' => (int) env('AUTH_OTP_MAX_ATTEMPTS', 5),
    ],
];
