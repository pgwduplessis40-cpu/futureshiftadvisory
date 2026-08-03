<?php

declare(strict_types=1);

use App\Services\OperationalHealth\OperationalHealthSchedule;

return [
    /*
    |--------------------------------------------------------------------------
    | Operational Health Checks
    |--------------------------------------------------------------------------
    |
    | This can be suspended per environment while maintenance is in progress.
    |
    */

    'enabled' => env('OPERATIONAL_HEALTH_CHECKS_ENABLED', true),

    'timezone' => env('OPERATIONAL_HEALTH_TIMEZONE', 'Pacific/Auckland'),

    'weekday_times' => explode(',', (string) env(
        'OPERATIONAL_HEALTH_WEEKDAY_TIMES',
        implode(',', OperationalHealthSchedule::DEFAULT_WEEKDAY_TIMES),
    )),

    'weekend_times' => explode(',', (string) env(
        'OPERATIONAL_HEALTH_WEEKEND_TIMES',
        implode(',', OperationalHealthSchedule::DEFAULT_WEEKEND_TIMES),
    )),

    'sentinel_enabled' => env('OPERATIONAL_HEALTH_SENTINEL_ENABLED', true),

    'require_verified_deployment' => env(
        'OPERATIONAL_HEALTH_REQUIRE_VERIFIED_DEPLOYMENT',
        ! in_array(env('APP_ENV', 'production'), ['local', 'testing'], true),
    ),

    'alerts' => [
        'enabled' => env('OPERATIONAL_HEALTH_ALERTS_ENABLED', true),
        'consecutive_failures' => (int) env('OPERATIONAL_HEALTH_ALERT_CONSECUTIVE_FAILURES', 2),
        'statuses' => [
            'failed',
            'warning',
            'skipped',
        ],
    ],

    'retention_days' => (int) env('OPERATIONAL_HEALTH_RETENTION_DAYS', 90),

    'ensure_fixtures' => env('OPERATIONAL_HEALTH_ENSURE_FIXTURES', true),

    'users' => [
        'super_admin_email' => env('OPERATIONAL_HEALTH_SUPER_ADMIN_EMAIL', 'operational-health-admin@futureshiftadvisory.test'),
        'client_email' => env('OPERATIONAL_HEALTH_CLIENT_EMAIL', 'operational-health-client@futureshiftadvisory.test'),
        'dd_client_email' => env(
            'OPERATIONAL_HEALTH_DD_CLIENT_EMAIL',
            env('OPERATIONAL_HEALTH_CLIENT_EMAIL', 'operational-health-dd-client@futureshiftadvisory.test'),
        ),
        'entrepreneur_email' => env('OPERATIONAL_HEALTH_ENTREPRENEUR_EMAIL', 'operational-health-entrepreneur@futureshiftadvisory.test'),
    ],
];
