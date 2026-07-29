<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Operational Health Checks
    |--------------------------------------------------------------------------
    |
    | Keep this disabled by default in production until the synthetic monitor
    | accounts and alert channels have been deliberately configured there.
    |
    */

    'enabled' => env('OPERATIONAL_HEALTH_CHECKS_ENABLED', env('APP_ENV', 'production') !== 'production'),

    'weekday_cron' => env('OPERATIONAL_HEALTH_WEEKDAY_CRON', '30 7-17 * * 1-5'),

    'weekend_cron' => env('OPERATIONAL_HEALTH_WEEKEND_CRON', '30 7 * * 0,6'),

    'retention_days' => (int) env('OPERATIONAL_HEALTH_RETENTION_DAYS', 90),

    'users' => [
        'super_admin_email' => env('OPERATIONAL_HEALTH_SUPER_ADMIN_EMAIL'),
        'client_email' => env('OPERATIONAL_HEALTH_CLIENT_EMAIL'),
        'entrepreneur_email' => env('OPERATIONAL_HEALTH_ENTREPRENEUR_EMAIL'),
    ],
];
