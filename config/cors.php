<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Mengizinkan dashboard admin (admin.perbasipemalang.org) dan domain
    | lain yang diperlukan untuk memanggil API & memuat resource dari
    | perbasipemalang.org.
    |
    */

    // Cover semua path (route Laravel 11 default tidak selalu di /api/*).
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://admin.perbasipemalang.org',
        'http://admin.perbasipemalang.org',
        'https://perbasipemalang.org',
        'http://perbasipemalang.org',
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:5173',
        'http://localhost:8080',
    ],

    'allowed_origins_patterns' => [
        '#^https?://([a-z0-9-]+\.)?perbasipemalang\.org$#i',
        '#^http://localhost(:\d+)?$#',
        '#^http://127\.0\.0\.1(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
