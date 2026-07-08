<?php

declare(strict_types=1);

$appEnv = env('APP_ENV', 'production');

return [
    'live' => env('FEATURE_VIRUS_SCAN_LIVE', false),

    'allow_noop' => in_array($appEnv, ['local', 'testing'], true)
        && (bool) env('VIRUS_SCAN_ALLOW_NOOP', true),

    'fail_open_on_error' => in_array($appEnv, ['local', 'testing'], true)
        && (bool) env('VIRUS_SCAN_ALLOW_NOOP', true)
        && (bool) env('VIRUS_SCAN_FAIL_OPEN_ON_ERROR', $appEnv === 'local'),

    'clamav' => [
        'host' => env('CLAMAV_HOST', '127.0.0.1'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'timeout_seconds' => (float) env('CLAMAV_TIMEOUT_SECONDS', 2),
        'chunk_size' => (int) env('CLAMAV_CHUNK_SIZE', 8192),
    ],

    'notice_cache_ttl_seconds' => (int) env('VIRUS_SCAN_NOTICE_TTL_SECONDS', 86400),
];
