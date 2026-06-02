<?php

declare(strict_types=1);

return [
    'service' => [
        'default_hourly_rate' => (float) env('FEE_SERVICE_DEFAULT_HOURLY_RATE', 250),
        'currency' => env('FEE_SERVICE_CURRENCY', 'NZD'),
    ],

    'sme' => [
        'retainer_monthly' => [
            'foundation' => (float) env('FEE_SME_FOUNDATION_RETAINER_MONTHLY', 1200),
            'growth' => (float) env('FEE_SME_GROWTH_RETAINER_MONTHLY', 2400),
            'scale' => (float) env('FEE_SME_SCALE_RETAINER_MONTHLY', 4000),
        ],
    ],

    'npo' => [
        'service_rate_discount_percent' => (float) env('FEE_NPO_SERVICE_RATE_DISCOUNT_PERCENT', 30),
        'retainer_discount_percent' => (float) env('FEE_NPO_RETAINER_DISCOUNT_PERCENT', 35),
        'bespoke_accountability_report_addon' => (float) env('FEE_NPO_ACCOUNTABILITY_REPORT_ADDON', 650),
        'pro_bono' => [
            'max_per_year' => (int) env('FEE_NPO_PRO_BONO_MAX_PER_YEAR', 2),
        ],
        'budget_bands' => [
            'micro' => [
                'max_budget' => 250000,
                'sme_tier' => 'foundation',
                'default_months' => 6,
            ],
            'small' => [
                'max_budget' => 1000000,
                'sme_tier' => 'foundation',
                'default_months' => 12,
            ],
            'medium' => [
                'max_budget' => 3000000,
                'sme_tier' => 'growth',
                'default_months' => 12,
            ],
            'large' => [
                'max_budget' => null,
                'sme_tier' => 'scale',
                'default_months' => 12,
            ],
        ],
    ],
];
