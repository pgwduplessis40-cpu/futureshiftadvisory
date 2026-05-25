<?php

declare(strict_types=1);

return [
    'min_cohort' => env('PRIVACY_MIN_COHORT', env('BENCHMARK_MIN_COHORT', 5)),
];
