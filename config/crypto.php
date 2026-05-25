<?php

declare(strict_types=1);

return [
    'pqc' => [
        'enabled' => (bool) env('FEATURE_PQC', false),
        'provider' => env('PQC_PROVIDER', 'software'),
        'key_id' => env('PQC_KEY_ID'),
    ],
];
