<?php

declare(strict_types=1);

return [
    // MFA is mandatory per CLAUDE.md / spec §4. The MFA_REQUIRED flag exists
    // only to let local/dev/testing turn it off while building. In production
    // it is ALWAYS true and the env var cannot disable it.
    'mfa_required' => env('APP_ENV') === 'production'
        ? true
        : filter_var(env('MFA_REQUIRED', true), FILTER_VALIDATE_BOOL),
    'invite_token_ttl_hours' => (int) env('INVITE_TOKEN_TTL_HOURS', 168),
    'mfa_failed_attempt_limit' => (int) env('MFA_FAILED_ATTEMPT_LIMIT', 3),
    'mfa_lockout_minutes' => (int) env('MFA_LOCKOUT_MINUTES', 15),
    'fresh_step_up_minutes' => (int) env('FRESH_STEP_UP_MINUTES', 5),
    'prospect_intake_secret' => env('PROSPECT_INTAKE_SECRET'),
    'prospect_intake_tolerance_seconds' => (int) env('PROSPECT_INTAKE_TOLERANCE_SECONDS', 300),
    'session_timeouts' => [
        'default' => (int) env('SESSION_TIMEOUT_DEFAULT_MINUTES', 30),
        'super_admin' => (int) env('SESSION_TIMEOUT_SUPER_ADMIN_MINUTES', 15),
        'advisor' => (int) env('SESSION_TIMEOUT_ADVISOR_MINUTES', 30),
        'junior_advisor' => (int) env('SESSION_TIMEOUT_JUNIOR_ADVISOR_MINUTES', 30),
        'entrepreneur_mentor' => (int) env('SESSION_TIMEOUT_ENTREPRENEUR_MENTOR_MINUTES', 30),
        'client_primary' => (int) env('SESSION_TIMEOUT_CLIENT_MINUTES', 60),
        'client_team' => (int) env('SESSION_TIMEOUT_CLIENT_MINUTES', 60),
        'entrepreneur' => (int) env('SESSION_TIMEOUT_ENTREPRENEUR_MINUTES', 60),
        'broker' => (int) env('SESSION_TIMEOUT_BROKER_MINUTES', 60),
        'coach' => (int) env('SESSION_TIMEOUT_COACH_MINUTES', 60),
    ],
    'step_up' => [
        'threshold' => (int) env('STEP_UP_RISK_THRESHOLD', 70),
        'signals' => [
            'ip_changed' => 30,
            'country_changed' => 50,
            'user_agent_changed' => 40,
            'super_admin_route_new_device' => 70,
        ],
    ],
];
