<?php

declare(strict_types=1);

return [
    'live' => env('FEATURE_VIRUS_SCAN_LIVE', false),

    'allow_noop' => env(
        'VIRUS_SCAN_ALLOW_NOOP',
        in_array(env('APP_ENV', 'production'), ['local', 'testing'], true),
    ),

    'clamav' => [
        'host' => env('CLAMAV_HOST', '127.0.0.1'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'timeout_seconds' => (float) env('CLAMAV_TIMEOUT_SECONDS', 2),
        'chunk_size' => (int) env('CLAMAV_CHUNK_SIZE', 8192),
    ],

    'notice_cache_ttl_seconds' => (int) env('VIRUS_SCAN_NOTICE_TTL_SECONDS', 86400),
];
