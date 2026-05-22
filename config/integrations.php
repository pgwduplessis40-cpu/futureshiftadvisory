<?php

declare(strict_types=1);

return [
    'retry' => [
        'attempts' => (int) env('INTEGRATION_RETRY_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('INTEGRATION_RETRY_BASE_DELAY_MS', 100),
        'max_delay_ms' => (int) env('INTEGRATION_RETRY_MAX_DELAY_MS', 1000),
        'retry_statuses' => [408, 409, 425, 429, 500, 502, 503, 504],
    ],

    'circuit_breaker' => [
        'failure_threshold' => (int) env('INTEGRATION_BREAKER_FAILURE_THRESHOLD', 5),
        'window_seconds' => (int) env('INTEGRATION_BREAKER_WINDOW_SECONDS', 60),
        'open_seconds' => (int) env('INTEGRATION_BREAKER_OPEN_SECONDS', 300),
    ],

    'cache' => [
        'ttl_seconds' => (int) env('INTEGRATION_CACHE_TTL_SECONDS', 900),
    ],

    'health' => [
        'green' => [
            'min_success_rate' => (float) env('INTEGRATION_HEALTH_GREEN_SUCCESS_RATE', 0.99),
            'max_p95_latency_ms' => (int) env('INTEGRATION_HEALTH_GREEN_P95_MS', 1000),
        ],
        'amber' => [
            'min_success_rate' => (float) env('INTEGRATION_HEALTH_AMBER_SUCCESS_RATE', 0.95),
            'max_p95_latency_ms' => (int) env('INTEGRATION_HEALTH_AMBER_P95_MS', 3000),
        ],
    ],

    'nzbn' => [
        'live' => (bool) env('FEATURE_NZBN_LIVE', false),
        'base_url' => env('NZBN_BASE_URL', 'https://api.business.govt.nz/services/v4'),
        'api_key' => env('NZBN_API_KEY'),
    ],

    'companies_office' => [
        'live' => (bool) env('FEATURE_COMPANIES_OFFICE_LIVE', false),
        'base_url' => env('COMPANIES_OFFICE_BASE_URL', 'https://api.business.govt.nz/services/v1/companies'),
        'api_key' => env('COMPANIES_OFFICE_API_KEY'),
    ],

    'ird' => [
        'live' => (bool) env('FEATURE_IRD_LIVE', false),
        'base_url' => env('IRD_BASE_URL', 'https://api.ird.govt.nz'),
        'api_key' => env('IRD_API_KEY'),
    ],

    'rbnz' => [
        'live' => (bool) env('FEATURE_RBNZ_LIVE', false),
        'base_url' => env('RBNZ_BASE_URL', 'https://api.rbnz.govt.nz'),
        'api_key' => env('RBNZ_API_KEY'),
    ],

    'stats_nz' => [
        'live' => (bool) env('FEATURE_STATS_NZ_LIVE', false),
        'base_url' => env('STATS_NZ_BASE_URL', 'https://api.stats.govt.nz'),
        'api_key' => env('STATS_NZ_API_KEY'),
    ],

    'mbie' => [
        'live' => (bool) env('FEATURE_MBIE_LIVE', false),
        'base_url' => env('MBIE_BASE_URL', 'https://api.mbie.govt.nz'),
        'api_key' => env('MBIE_API_KEY'),
    ],
];
