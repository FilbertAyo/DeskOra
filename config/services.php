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
        'token' => env('POSTMARK_TOKEN'),
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

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),
        'max_tokens' => env('GEMINI_MAX_TOKENS', 500),
        'summary_max_tokens' => env('GEMINI_SUMMARY_MAX_TOKENS', 800),
        'question_max_tokens' => env('GEMINI_QUESTION_MAX_TOKENS', 400),
        'timeout' => env('GEMINI_TIMEOUT', 30),
        'summary_cache_enabled' => filter_var(env('GEMINI_SUMMARY_CACHE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'summary_cache_seconds' => env('GEMINI_SUMMARY_CACHE_SECONDS', 604800),
        'max_requests_per_minute' => env('GEMINI_MAX_REQUESTS_PER_MINUTE', 5),
    ],

];
