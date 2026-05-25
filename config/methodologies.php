<?php

declare(strict_types=1);
use App\Services\Dashboards\BusinessHealthRadarBuilder;
use App\Services\Dashboards\ClientEngagementScorer;
use App\Services\Payments\PaymentProcessor;

return [
    'config_ref_allowlist' => [
        'dashboards.*',
        'integrations.health.*',
        'integrations.retry.*',
        'integrations.circuit_breaker.*',
        'integrations.cache.ttl_seconds',
        'integrations.payments.max_attempts',
        'integrations.payments.retry_delay_minutes',
        'integrations.payments.webhook_tolerance_seconds',
        'clients.capacity.*',
        'entrepreneurs.capacity.*',
        'privacy.min_cohort',
        'proposals.expiry_days',
    ],

    'config_ref_sensitive_patterns' => [
        '*secret*',
        '*key*',
        '*token*',
        '*password*',
        '*credential*',
        '*.api_*',
    ],

    'feature_labels' => [
        'advisor.dashboard.engagement' => 'Advisor dashboard engagement panel',
        'advisor.dashboard.business_health' => 'Advisor dashboard business health radar',
        'client.portal.business_health' => 'Client portal business health radar',
        'advisor.payments.retry' => 'Advisor payment retry workflow',
    ],

    'entries' => [
        'engagement.score' => [
            'id' => 'engagement.score',
            'area' => 'Engagement',
            'name' => 'Client Engagement Score',
            'summary' => 'Combines questionnaire progress, document activity, milestone status, and communication recency into an internal engagement status.',
            'formula' => 'Weighted sub-scores are summed into a 0-100 score and compared with the configured green and amber thresholds.',
            'inputs' => [
                'Questionnaire completion percentage',
                'Verified document coverage',
                'Milestones on-track percentage',
                'Days since latest client communication',
            ],
            'config_refs' => [
                'dashboards.engagement.weights',
                'dashboards.engagement.thresholds',
                'dashboards.engagement.comms_decay_days',
            ],
            'where_used' => [
                'advisor.dashboard.engagement',
            ],
            'sources' => [
                'PLAN-DASHBOARD-INTERACTIVITY.md',
            ],
            'owning_service' => ClientEngagementScorer::class,
            'version' => '2026-05-wo-m01',
            'internal_only' => true,
        ],

        'radar.dimension_score' => [
            'id' => 'radar.dimension_score',
            'area' => 'Health radar',
            'name' => 'Business Health Radar Dimension Score',
            'summary' => 'Maps finding severity into dimension load and converts it into a 0-100 radar score for business health views.',
            'formula' => 'Severity weights are summed per configured dimension, capped by the load cap, then inverted into a 0-100 health score.',
            'inputs' => [
                'Analysis findings grouped by configured radar dimension',
                'Finding severity',
                'Configured severity weights and load cap',
            ],
            'config_refs' => [
                'dashboards.radar.severity_weights',
                'dashboards.radar.load_cap',
            ],
            'where_used' => [
                'advisor.dashboard.business_health',
                'client.portal.business_health',
            ],
            'sources' => [
                'PLAN-DASHBOARD-INTERACTIVITY.md',
            ],
            'owning_service' => BusinessHealthRadarBuilder::class,
            'version' => '2026-05-wo-m01',
            'internal_only' => true,
        ],

        'payments.retry_policy' => [
            'id' => 'payments.retry_policy',
            'area' => 'Payments',
            'name' => 'Payment Retry Policy',
            'summary' => 'Controls how failed scheduled payments become retryable and how long the platform waits before retry attempts.',
            'formula' => 'Retry availability is bounded by the configured maximum attempts and retry delay interval.',
            'inputs' => [
                'Latest payment status',
                'Payment attempt count',
                'Configured maximum attempts',
                'Configured retry delay in minutes',
            ],
            'config_refs' => [
                'integrations.payments.max_attempts',
                'integrations.payments.retry_delay_minutes',
            ],
            'where_used' => [
                'advisor.payments.retry',
            ],
            'sources' => [],
            'owning_service' => PaymentProcessor::class,
            'version' => '2026-05-wo-m01',
            'internal_only' => true,
        ],
    ],
];
