<?php

declare(strict_types=1);

return [
    'engagement' => [
        'weights' => [
            'questionnaire_pct' => (float) env('DASHBOARD_ENGAGEMENT_QUESTIONNAIRE_WEIGHT', 0.30),
            'documents_pct' => (float) env('DASHBOARD_ENGAGEMENT_DOCUMENTS_WEIGHT', 0.30),
            'milestones_on_track_pct' => (float) env('DASHBOARD_ENGAGEMENT_MILESTONES_WEIGHT', 0.25),
            'comms_recency_pct' => (float) env('DASHBOARD_ENGAGEMENT_COMMS_WEIGHT', 0.15),
        ],
        'thresholds' => [
            'green' => (int) env('DASHBOARD_ENGAGEMENT_GREEN_MIN', 75),
            'amber' => (int) env('DASHBOARD_ENGAGEMENT_AMBER_MIN', 50),
        ],
        'comms_decay_days' => (int) env('DASHBOARD_ENGAGEMENT_COMMS_DECAY_DAYS', 30),
    ],

    'radar' => [
        'severity_weights' => [
            'info' => 0,
            'low' => 1,
            'medium' => 3,
            'high' => 7,
            'critical' => 15,
        ],
        'load_cap' => (int) env('DASHBOARD_RADAR_LOAD_CAP', 30),
        'dimensions' => [
            'financial' => ['financial'],
            'operational' => ['operational', 'systems'],
            'people' => ['hr'],
            'strategic' => ['swot', 'competitor', 'website_audit'],
            'compliance' => ['compliance', 'regulatory_impact', 'insurance_risk'],
        ],
    ],

    'economic_exposure' => [
        'cpi' => [
            'supported' => true,
            'source' => 'active_clients',
        ],
        'ocr' => [
            'supported' => true,
            'source' => 'financial_snapshots',
            'debt_paths' => [
                'metrics.interest_bearing_debt',
                'metrics.total_debt',
                'balance_sheet.interest_bearing_debt',
                'balance_sheet.total_liabilities',
            ],
        ],
        'wage' => [
            'supported' => false,
            'reason' => 'classification_not_captured',
        ],
        'fx' => [
            'supported' => false,
            'reason' => 'classification_not_captured',
        ],
    ],
];
