<?php

declare(strict_types=1);

return [
    'mfa_required' => env('MFA_REQUIRED', true),
    'invite_token_ttl_hours' => (int) env('INVITE_TOKEN_TTL_HOURS', 72),
];
