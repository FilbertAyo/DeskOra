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

    'groq' => [
        'api_key' => trim(trim((string) env('GROQ_API_KEY', '')), '"\''),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'max_tokens' => env('GROQ_MAX_TOKENS', 500),
        'summary_max_tokens' => env('GROQ_SUMMARY_MAX_TOKENS', 800),
        'question_max_tokens' => env('GROQ_QUESTION_MAX_TOKENS', 400),
        'timeout' => env('GROQ_TIMEOUT', 30),
        'summary_cache_enabled' => filter_var(env('GROQ_SUMMARY_CACHE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'summary_cache_seconds' => env('GROQ_SUMMARY_CACHE_SECONDS', 604800),
        'max_requests_per_minute' => env('GROQ_MAX_REQUESTS_PER_MINUTE', 30),
        'assistant_max_tokens' => env('GROQ_ASSISTANT_MAX_TOKENS', 600),
        'assistant_max_requests_per_minute' => env('GROQ_ASSISTANT_MAX_REQUESTS_PER_MINUTE', 10),
    ],

];
