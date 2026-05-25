<?php

declare(strict_types=1);

return [
    'driver' => env('HSM_DRIVER', 'software'),
    'key_id' => env('HSM_KEY_ID'),
    'software_key' => env('HSM_SOFTWARE_KEY'),
    'direct_secret_max_bytes' => (int) env('HSM_DIRECT_SECRET_MAX_BYTES', 4096),
];
