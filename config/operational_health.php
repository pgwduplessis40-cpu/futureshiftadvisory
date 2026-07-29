<?php

declare(strict_types=1);

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

    'weekday_cron' => env('OPERATIONAL_HEALTH_WEEKDAY_CRON', '30 7-17 * * 1-5'),

    'weekend_cron' => env('OPERATIONAL_HEALTH_WEEKEND_CRON', '30 7 * * 0,6'),

    'retention_days' => (int) env('OPERATIONAL_HEALTH_RETENTION_DAYS', 90),

    'users' => [
        'super_admin_email' => env('OPERATIONAL_HEALTH_SUPER_ADMIN_EMAIL'),
        'client_email' => env('OPERATIONAL_HEALTH_CLIENT_EMAIL'),
        'entrepreneur_email' => env('OPERATIONAL_HEALTH_ENTREPRENEUR_EMAIL'),
    ],
];
