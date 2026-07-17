<?php

declare(strict_types=1);

return [
    'capacity' => [
        'limit' => env('ENTREPRENEUR_ADVISOR_CAPACITY_LIMIT', 30),
        'warning_threshold' => env('ENTREPRENEUR_ADVISOR_CAPACITY_WARNING', 24),
    ],
    'idea_viability' => [
        'minimum_field_words' => env('ENTREPRENEUR_IDEA_MINIMUM_FIELD_WORDS', 6),
        'minimum_demand_signal_words' => env('ENTREPRENEUR_IDEA_MINIMUM_DEMAND_WORDS', 8),
    ],
    'budget' => [
        'runway_mismatch_tolerance_months' => env('ENTREPRENEUR_BUDGET_RUNWAY_MISMATCH_TOLERANCE_MONTHS', 2),
        'snapshot_revenue_variance_threshold' => env('ENTREPRENEUR_BUDGET_SNAPSHOT_REVENUE_VARIANCE_THRESHOLD', 0.2),
    ],
    'benchmark_min_cohort' => env('BENCHMARK_MIN_COHORT', env('PRIVACY_MIN_COHORT', 5)),
];
