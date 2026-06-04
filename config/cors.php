<?php

$allowedOrigins = array_values(array_filter([
    env('FRONTEND_URL', 'http://localhost:3000'),
    env('FRONTEND_URL_ALT'),
]));

return [
    'paths' => [
        'api/*',
        'connection/access',
        'connection/logout',
        'connection/session',
    ],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [
        'X-Access-Token',
        'X-Access-Token-Expires-In',
        'X-Access-Token-Expires-At',
    ],
    'max_age' => 0,
    'supports_credentials' => true,
];
