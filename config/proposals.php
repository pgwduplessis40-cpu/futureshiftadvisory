<?php

declare(strict_types=1);

return [
    'expiry_days' => max(1, (int) env('PROPOSAL_EXPIRY_DAYS', 30)),
];
