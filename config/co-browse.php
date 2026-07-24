<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(env('CO_BROWSE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'request_timeout_seconds' => (int) env('CO_BROWSE_REQUEST_TIMEOUT_SECONDS', 60),
    'max_duration_minutes' => (int) env('CO_BROWSE_MAX_DURATION_MINUTES', 20),
    'heartbeat_interval_seconds' => (int) env('CO_BROWSE_HEARTBEAT_INTERVAL_SECONDS', 10),
    'presence_ttl_seconds' => (int) env('CO_BROWSE_PRESENCE_TTL_SECONDS', 120),
    'portal_context_ttl_seconds' => (int) env('CO_BROWSE_PORTAL_CONTEXT_TTL_SECONDS', 300),
    'actions_per_second' => (int) env('CO_BROWSE_ACTIONS_PER_SECOND', 5),
];
