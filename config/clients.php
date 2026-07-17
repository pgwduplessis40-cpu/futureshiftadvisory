<?php

declare(strict_types=1);

return [
    'capacity' => [
        'limit' => env('CLIENT_ADVISOR_CAPACITY_LIMIT', 30),
        'warning_ratio' => env('CLIENT_ADVISOR_CAPACITY_WARNING_RATIO', 0.8),
    ],
];
