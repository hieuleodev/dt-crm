<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // Thông tin xác thực Sapo chỉ dùng ở máy chủ. Redirect URI dành cho chế độ OAuth.
    'sapo' => [
        'store_domain' => env('SAPO_STORE_DOMAIN'),
        'auth_mode' => env('SAPO_AUTH_MODE', 'oauth'),
        'access_token' => env('SAPO_ACCESS_TOKEN'),
        'client_id' => env('SAPO_CLIENT_ID'),
        'client_secret' => env('SAPO_CLIENT_SECRET'),
        'redirect_uri' => env('SAPO_REDIRECT_URI'),
    ],

];
