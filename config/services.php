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

    'exchangeRate' => [
        'domain' => env('EXCHANGERATE_URI', ''),
        'history' => env('EXCHANGERATE_HISTORY_URI', ''),
        'apiKey' => env('EXCHANGERATE_API_KEY', ''),
    ],

    'notification' => [
        'url' => env('NOTIFICATION_SERVICE_URL', ''),
        'key' => env('NOTIFICATION_SERVICE_KEY', ''),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
        'api_version' => env('GEMINI_API_VERSION', 'v1beta'),
        'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-2.0-flash'),
    ],

];
