<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:3000'),
    ],

    'firebase' => [
        'web_api_key' => env('FIREBASE_WEB_API_KEY'),
    ],

    'sonicpesa' => [
        'base_url' => env('SONICPESA_BASE_URL', 'https://api.sonicpesa.com'),
        'api_key' => env('SONICPESA_API_KEY'),
        'api_secret' => env('SONICPESA_API_SECRET'),
    ],

    'mobilipa' => [
        'base_url' => env('MOBILIPA_BASE_URL', 'https://api.mobilipa.store'),
        'api_key' => env('MOBILIPA_API_KEY'),
    ],

];
