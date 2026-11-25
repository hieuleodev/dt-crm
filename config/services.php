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

    // Cấu hình Zalo Official Account dùng cho gửi Tin nhắn Broadcast / Truyền thông đa bài viết.
    'zalo_oa' => [
        'app_id' => env('ZALO_OA_APP_ID'),
        'app_secret' => env('ZALO_OA_APP_SECRET'),
        'redirect_uri' => env('ZALO_OA_REDIRECT_URI'),
        'webhook_secret' => env('ZALO_OA_WEBHOOK_SECRET', env('ZALO_OA_APP_SECRET')),
        'oa_id' => env('ZALO_OA_ID'),
        'access_token' => env('ZALO_OA_ACCESS_TOKEN'),
        'oauth_base_url' => env('ZALO_OA_OAUTH_BASE_URL', 'https://oauth.zaloapp.com/v4/oa'),
        'api_base_url' => env('ZALO_OA_API_BASE_URL', 'https://openapi.zalo.me/v3.0/oa'),
        'sandbox' => (bool) env('ZALO_OA_SANDBOX', false),
    ],

];
