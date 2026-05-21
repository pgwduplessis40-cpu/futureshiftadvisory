<?php

declare(strict_types=1);

return [
    'capacity' => [
        'limit' => env('ENTREPRENEUR_ADVISOR_CAPACITY_LIMIT', 30),
        'warning_threshold' => env('ENTREPRENEUR_ADVISOR_CAPACITY_WARNING', 24),
    ],
];
