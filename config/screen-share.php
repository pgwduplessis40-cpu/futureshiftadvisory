<?php

declare(strict_types=1);

return [
    'request_timeout_seconds' => (int) env('SCREEN_SHARE_REQUEST_TIMEOUT_SECONDS', 60),
    'picker_timeout_seconds' => (int) env('SCREEN_SHARE_PICKER_TIMEOUT_SECONDS', 90),
    'max_duration_minutes' => (int) env('SCREEN_SHARE_MAX_DURATION_MINUTES', 30),
    'warning_at_minutes' => (int) env('SCREEN_SHARE_WARNING_AT_MINUTES', 25),
    'heartbeat_interval_seconds' => (int) env('SCREEN_SHARE_HEARTBEAT_INTERVAL_SECONDS', 10),
    'reconnect_grace_seconds' => (int) env('SCREEN_SHARE_RECONNECT_GRACE_SECONDS', 15),
    'presence_ttl_seconds' => (int) env('SCREEN_SHARE_PRESENCE_TTL_SECONDS', 45),
    'portal_context_ttl_seconds' => (int) env('SCREEN_SHARE_PORTAL_CONTEXT_TTL_SECONDS', 300),
    'stun_urls' => env('SCREEN_SHARE_STUN_URLS', ''),
    'turn_urls' => env('SCREEN_SHARE_TURN_URLS', ''),
    'turn_shared_secret' => env('SCREEN_SHARE_TURN_SHARED_SECRET', ''),
    'turn_ttl_seconds' => (int) env('SCREEN_SHARE_TURN_TTL_SECONDS', 600),
];
