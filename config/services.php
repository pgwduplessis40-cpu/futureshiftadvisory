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

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'admin_key' => env('ANTHROPIC_ADMIN_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        'max_output_tokens' => (int) env('ANTHROPIC_MAX_OUTPUT_TOKENS', 4096),
        'endpoint' => env('ANTHROPIC_ENDPOINT', 'https://api.anthropic.com/v1/messages'),
        'timeout_seconds' => (int) env('ANTHROPIC_TIMEOUT_SECONDS', 60),
        'retry_attempts' => (int) env('ANTHROPIC_RETRY_ATTEMPTS', 1),
        'refresh_stale_minutes' => (int) env('ANTHROPIC_REFRESH_STALE_MINUTES', 2),
    ],

    'whisper' => [
        'live' => (bool) env('FEATURE_WHISPER_LIVE', false),
        'endpoint' => env('WHISPER_ENDPOINT', 'https://api.openai.com/v1/audio/transcriptions'),
    ],

    'browsershot' => [
        'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
        'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
        'timeout_seconds' => (int) env('BROWSERSHOT_TIMEOUT_SECONDS', 60),
    ],

    'google_analytics' => [
        'measurement_id' => env('GOOGLE_ANALYTICS_MEASUREMENT_ID'),
    ],

];
