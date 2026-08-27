<?php

declare(strict_types=1);

return [
    'client_error' => [
        // Configure the production log router to alert on the structured
        // `client_error_fingerprint.new` message after every deployment.
        'channel' => env('CLIENT_ERROR_TELEMETRY_CHANNEL', 'stack'),
    ],
];
