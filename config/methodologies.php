<?php

declare(strict_types=1);

use App\Services\Analysis\AnalysisRunner;
use App\Services\Analytics\FunnelTracker;
use App\Services\Dashboards\BusinessHealthRadarBuilder;
use App\Services\Dashboards\ClientEngagementScorer;
use App\Services\Dashboards\EconomicExposureMapper;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\DataQuality\QuestionnaireCompletenessCalculator;
use App\Services\Dd\FxNormaliser;
use App\Services\Dd\Valuation as DdValuationService;
use App\Services\Entrepreneurs\AdvisoryReadiness;
use App\Services\Entrepreneurs\Assessment;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Entrepreneurs\RatingFrameworkManager;
use App\Services\Entrepreneurs\Readiness;
use App\Services\Entrepreneurs\Revision;
use App\Services\Fees\FeeCalculator;
use App\Services\Integration\IntegrationHealthBander;
use App\Services\Panels\Coach\SignalDetector as CoachSignalDetector;
use App\Services\Payments\PaymentProcessor;
use App\Services\Pv\BusinessValuation as BusinessValuationService;
use App\Services\Pv\DiscountRateResolver;
use App\Services\Pv\ImprovementPv;
use App\Services\Pv\PvEngine;
use App\Services\Pv\PvWaterfallBuilder;
use App\Services\Pv\RiskCostPv;
use App\Services\Reports\ReportComposer;
use App\Services\Wellbeing\CoachingSignalDetector;
use App\Services\Wellbeing\WellbeingTrendAnalytics;

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
        'admin.integration_health' => 'Admin integration health monitor',
        'advisor.analysis.findings' => 'Advisor analysis findings workflow',
        'advisor.analytics.funnel' => 'Advisor funnel analytics panel',
        'advisor.coach.referrals' => 'Advisor coach referral panel',
        'advisor.dashboard.business_health' => 'Advisor dashboard business health radar',
        'advisor.dashboard.economic_exposure' => 'Advisor dashboard economic exposure panel',
        'advisor.dashboard.engagement' => 'Advisor dashboard engagement panel',
        'advisor.data_quality' => 'Advisor data quality gate',
        'advisor.dd.risk_register' => 'Advisor DD risk register',
        'advisor.dd.valuation' => 'Advisor DD valuation workflow',
        'advisor.entrepreneurs.advisory_readiness' => 'Advisor entrepreneur advisory readiness signal',
        'advisor.entrepreneurs.rating_framework' => 'Advisor entrepreneur rating framework manager',
        'advisor.fees.calculator' => 'Advisor fee calculator',
        'advisor.payments.retry' => 'Advisor payment retry workflow',
        'advisor.pv.calculations' => 'Advisor present-value calculations',
        'advisor.pv.waterfall' => 'Advisor PV waterfall dashboard',
        'advisor.valuation.business' => 'Advisor business valuation workflow',
        'advisor.wellbeing.signals' => 'Advisor wellbeing signal workflow',
        'advisor.wellbeing.trends' => 'Advisor wellbeing trends dashboard',
        'client.portal.business_health' => 'Client portal business health radar',
        'entrepreneur.portal.idea_validation' => 'Entrepreneur idea validation workflow',
        'entrepreneur.portal.plan_assessment' => 'Entrepreneur plan assessment workflow',
        'entrepreneur.portal.readiness' => 'Entrepreneur readiness assessment',
        'entrepreneur.portal.revision_progress' => 'Entrepreneur revision progress workflow',
    ],

    'entries' => [
        'valuation.business' => [
            'id' => 'valuation.business',
            'area' => 'Valuation',
            'name' => 'Business Valuation Reconciliation',
            'summary' => 'Combines SDE multiple, EBITDA multiple, DCF value, and advisor adjustments into a reconciled valuation range.',
            'formula' => 'SDE and EBITDA ranges are derived from industry multiples; DCF uses the PV engine plus terminal value; reconciled low/mid/high equals the average method range plus adjustment total.',
            'inputs' => [
                'Financial snapshot or questionnaire financial inputs',
                'Industry multiple range',
                'Projected cash flows and terminal growth rate',
                'Advisor adjustments',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.valuation.business',
                'advisor.dd.valuation',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => BusinessValuationService::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'pv.dcf' => [
            'id' => 'pv.dcf',
            'area' => 'Present value',
            'name' => 'Discounted Cash Flow Present Value',
            'summary' => 'Discounts each cash-flow period to a present value and sums the rounded result.',
            'formula' => 'PV = sum(cash_flow_t / (1 + discount_rate)^t), using period t from the cash-flow key when numeric and rounding the total to 2 decimal places.',
            'inputs' => [
                'Cash-flow amount by period',
                'Discount rate',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.pv.calculations',
                'advisor.valuation.business',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => PvEngine::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'pv.terminal_value' => [
            'id' => 'pv.terminal_value',
            'area' => 'Present value',
            'name' => 'Terminal Value',
            'summary' => 'Calculates a Gordon-style terminal value and discounts it back to the valuation period.',
            'formula' => 'Terminal value = (terminal_cash_flow * (1 + growth_rate)) / (discount_rate - growth_rate), then discounted by (1 + discount_rate)^period and rounded to 2 decimals.',
            'inputs' => [
                'Terminal cash flow',
                'Discount rate',
                'Growth rate',
                'Terminal period',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.pv.calculations',
                'advisor.valuation.business',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => PvEngine::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'pv.discount_rate' => [
            'id' => 'pv.discount_rate',
            'area' => 'Present value',
            'name' => 'Discount Rate Resolution',
            'summary' => 'Resolves OCR-linked, industry WACC, advisor configured, or client inputted discount rates with attribution.',
            'formula' => 'OCR-linked rate uses latest OCR / 100 plus risk premium; industry WACC uses supplied rate or latest active industry WACC; advisor/client methods require an explicit rate and rationale.',
            'inputs' => [
                'Discount method',
                'Latest OCR indicator or industry WACC record',
                'Advisor or client supplied rate and rationale',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.pv.calculations',
                'advisor.valuation.business',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => DiscountRateResolver::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'pv.improvement' => [
            'id' => 'pv.improvement',
            'area' => 'Present value',
            'name' => 'Improvement Opportunity PV',
            'summary' => 'Ranks improvement opportunities by the present value of repeated annual benefit cash flows.',
            'formula' => 'Annual benefit is repeated for the bounded duration, discounted by the PV engine, then opportunities are ranked by PV of impact descending.',
            'inputs' => [
                'Annual benefit',
                'Duration years',
                'Discount method and discount options',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.pv.calculations',
                'advisor.pv.waterfall',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => ImprovementPv::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'pv.risk_cost' => [
            'id' => 'pv.risk_cost',
            'area' => 'Present value',
            'name' => 'Risk Cost PV',
            'summary' => 'Ranks risk costs by expected annual cost discounted across the risk duration.',
            'formula' => 'Applied impact = max(financial impact, statutory penalty midpoint); annual expected cost = applied impact * probability; PV is calculated over the bounded duration and ranked descending.',
            'inputs' => [
                'Financial impact',
                'Probability',
                'Duration years',
                'Statutory penalty range',
                'Discount method and discount options',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.pv.calculations',
                'advisor.pv.waterfall',
                'advisor.dd.risk_register',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => RiskCostPv::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'pv.waterfall' => [
            'id' => 'pv.waterfall',
            'area' => 'Present value',
            'name' => 'PV Waterfall',
            'summary' => 'Builds current-to-target PV bridge values from valuation, improvement PV, and risk mitigation PV.',
            'formula' => 'Target PV = current valuation midpoint + total improvement PV + total risk mitigation PV; up to eight ranked recommendation steps are shown before a remainder step.',
            'inputs' => [
                'Latest business valuation midpoint',
                'Ranked improvement opportunities',
                'Ranked risk costs',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.pv.waterfall',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => PvWaterfallBuilder::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'fees.hours_based' => [
            'id' => 'fees.hours_based',
            'area' => 'Fees',
            'name' => 'Hours-Based Fee',
            'summary' => 'Calculates service fee from advisor-entered service hours and rates.',
            'formula' => 'Fee = sum(service_hours * service_rate) across services; optional retainer monthly fee divides the total by retainer months.',
            'inputs' => [
                'Service hours',
                'Service rates',
                'Retainer conversion flag and months',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.fees.calculator',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => FeeCalculator::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'fees.outcome_based' => [
            'id' => 'fees.outcome_based',
            'area' => 'Fees',
            'name' => 'Outcome-Based Fee',
            'summary' => 'Suggests fees from PV opportunity, revenue component, and complexity multiplier.',
            'formula' => 'Mid fee = max(2500, ((improvement PV + risk PV) * value share + latest revenue * revenue component rate) * complexity multiplier); low/high are 80% and 120% of mid.',
            'inputs' => [
                'Improvement PV total',
                'Risk-cost PV total',
                'Value share',
                'Latest revenue',
                'Revenue component rate',
                'Complexity multiplier',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.fees.calculator',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => FeeCalculator::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'fees.entrepreneur' => [
            'id' => 'fees.entrepreneur',
            'area' => 'Fees',
            'name' => 'Entrepreneur Fee Bands',
            'summary' => 'Suggests fixed low/mid/high fee bands by entrepreneur stage.',
            'formula' => 'Idea stage uses 750/1500/2250; growth stage uses 2000/3500/5000; all other stages use 1200/2400/3600.',
            'inputs' => [
                'Entrepreneur stage',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.fees.calculator',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => FeeCalculator::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'data_quality.questionnaire_completeness' => [
            'id' => 'data_quality.questionnaire_completeness',
            'area' => 'Data quality',
            'name' => 'Questionnaire Completeness',
            'summary' => 'Measures answered visible questionnaire questions after conditional visibility rules are applied.',
            'formula' => 'Completeness = answered visible questions / expected visible questions * 100, with file attach answers counted only when document ids are attached.',
            'inputs' => [
                'Questionnaire responses',
                'Questionnaire visibility rules',
                'Question answers and attached document ids',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.data_quality',
                'advisor.dashboard.engagement',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => QuestionnaireCompletenessCalculator::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'data_quality.score' => [
            'id' => 'data_quality.score',
            'area' => 'Data quality',
            'name' => 'Data Quality Score',
            'summary' => 'Combines questionnaire completeness, answer support, verified documents, and freshness into a data quality band.',
            'formula' => 'Weighted score = questionnaire completeness 35% + answer support 25% + verified documents 25% + freshness 15%; score is capped at 39 when responses are absent or blocking verifications exist.',
            'inputs' => [
                'Questionnaire responses',
                'Visible client documents',
                'Document verifications',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.data_quality',
                'advisor.analysis.findings',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => DataQualityScorer::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

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
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => ClientEngagementScorer::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'radar.dimension_score' => [
            'id' => 'radar.dimension_score',
            'area' => 'Health radar',
            'name' => 'Business Health Radar Dimension Score',
            'summary' => 'Maps finding severity into dimension load and converts it into a 0-100 radar score for business health views.',
            'formula' => 'Severity weights are summed per configured dimension, capped by the load cap, then inverted into score = max(0, min(100, 100 - round(100 * load / load cap))).',
            'inputs' => [
                'Analysis findings grouped by configured radar dimension',
                'Finding severity',
                'Configured severity weights and load cap',
            ],
            'config_refs' => [
                'dashboards.radar.dimensions',
                'dashboards.radar.severity_weights',
                'dashboards.radar.load_cap',
            ],
            'where_used' => [
                'advisor.dashboard.business_health',
                'client.portal.business_health',
            ],
            'sources' => [
                'PLAN-DASHBOARD-INTERACTIVITY.md',
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => BusinessHealthRadarBuilder::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'economic.exposure' => [
            'id' => 'economic.exposure',
            'area' => 'Dashboards',
            'name' => 'Economic Exposure Mapping',
            'summary' => 'Classifies active clients by exposure to supported economic indicators for the advisor dashboard.',
            'formula' => 'CPI marks all active clients as exposed; OCR uses latest financial snapshot debt paths and marks clients exposed when debt is positive, unknown when snapshot or debt path is missing.',
            'inputs' => [
                'Active client ids',
                'Latest financial snapshots',
                'Configured OCR debt paths',
            ],
            'config_refs' => [
                'dashboards.economic_exposure.ocr.debt_paths',
            ],
            'where_used' => [
                'advisor.dashboard.economic_exposure',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => EconomicExposureMapper::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'funnel.drop_off' => [
            'id' => 'funnel.drop_off',
            'area' => 'Analytics',
            'name' => 'Funnel Drop-Off Rate',
            'summary' => 'Summarises entered, completed, abandoned, and returned funnel activity by step.',
            'formula' => 'Drop-off rate = (entered - completed) / entered for each step; worst drop-off is the highest step rate in the selected window.',
            'inputs' => [
                'Funnel events',
                'Selected client ids',
                'Window start',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.analytics.funnel',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => FunnelTracker::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'analysis.lens_model' => [
            'id' => 'analysis.lens_model',
            'area' => 'Analysis',
            'name' => 'Analysis Lens Model',
            'summary' => 'Normalises module findings into framework lenses used throughout advisor analysis.',
            'formula' => 'Module-provided lens values are normalised through the analytical framework before findings are persisted and reported.',
            'inputs' => [
                'Analysis module output',
                'Analytical framework lens map',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.analysis.findings',
                'advisor.dashboard.business_health',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => AnalysisRunner::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'analysis.finding_severity' => [
            'id' => 'analysis.finding_severity',
            'area' => 'Analysis',
            'name' => 'Finding Severity Classification',
            'summary' => 'Persists and applies module-produced severity values that drive dashboards, risk views, and DD outputs.',
            'formula' => 'Validated module finding severity is persisted as the finding severity enum and reused by downstream radar scoring, risk levels, and reporting.',
            'inputs' => [
                'Analysis module output',
                'Finding severity value',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.analysis.findings',
                'advisor.dashboard.business_health',
                'advisor.dd.risk_register',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => AnalysisRunner::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'dd.valuation' => [
            'id' => 'dd.valuation',
            'area' => 'Due diligence',
            'name' => 'DD Valuation Position',
            'summary' => 'Calculates DD valuation and buyer position from business valuation, FX normalisation, and asking price.',
            'formula' => 'Asking price is converted to NZD; below reconciled low is buyer_favourable, above reconciled high is renegotiate_or_walkaway, within range is within_range, and missing price is no_asking_price.',
            'inputs' => [
                'DD engagement financial inputs',
                'Business valuation',
                'Source currency and FX rate',
                'Asking price',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.dd.valuation',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => DdValuationService::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'dd.fx_normalisation' => [
            'id' => 'dd.fx_normalisation',
            'area' => 'Due diligence',
            'name' => 'DD FX Normalisation',
            'summary' => 'Normalises valuation ranges into NZD and produces sensitivity bands.',
            'formula' => 'NZD source values use a 1.0 rate; non-NZD values use 1 / latest NZD quote rate; sensitivity values are recalculated at -10%, base, and +10% source-to-NZD rates.',
            'inputs' => [
                'Business valuation values',
                'Source currency',
                'Latest exchange rate',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.dd.valuation',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => FxNormaliser::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'dd.risk_register' => [
            'id' => 'dd.risk_register',
            'area' => 'Due diligence',
            'name' => 'DD Risk Register',
            'summary' => 'Transforms DD findings into risk register rows with PV cost and recommendation context.',
            'formula' => 'Finding severity determines impact and probability, risk PV is calculated from expected annual cost, and severity maps to deal-killer, major, minor, or informational risk levels.',
            'inputs' => [
                'DD findings',
                'Finding severity',
                'DD valuation midpoint',
                'Risk-cost PV calculation',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.dd.risk_register',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => ReportComposer::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'dd.price_adjustment' => [
            'id' => 'dd.price_adjustment',
            'area' => 'Due diligence',
            'name' => 'DD Price Adjustment',
            'summary' => 'Calculates suggested price adjustment from DD risk level and PV of cost.',
            'formula' => 'Price adjustment = PV of cost * 1.0 for deal-killer, * 0.60 for major, * 0.20 for minor, and * 0.0 for informational risk.',
            'inputs' => [
                'DD risk level',
                'PV of cost',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.dd.risk_register',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => ReportComposer::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'integration.health.banding' => [
            'id' => 'integration.health.banding',
            'area' => 'Integrations',
            'name' => 'Integration Health Banding',
            'summary' => 'Bands integration service health as green, amber, or red from success rate and p95 latency.',
            'formula' => 'Green requires success rate >= configured green minimum and p95 latency <= green maximum; amber requires success rate >= amber minimum and p95 latency <= amber maximum; otherwise red.',
            'inputs' => [
                'Success rate',
                'P95 latency in milliseconds',
                'Configured green and amber thresholds',
            ],
            'config_refs' => [
                'integrations.health.green.min_success_rate',
                'integrations.health.green.max_p95_latency_ms',
                'integrations.health.amber.min_success_rate',
                'integrations.health.amber.max_p95_latency_ms',
            ],
            'where_used' => [
                'admin.integration_health',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => IntegrationHealthBander::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'entrepreneur.rating_framework' => [
            'id' => 'entrepreneur.rating_framework',
            'area' => 'Entrepreneurs',
            'name' => 'Entrepreneur Rating Framework',
            'summary' => 'Manages rating framework versions, criteria weights, grade bands, and publishing readiness.',
            'formula' => 'Published frameworks expose weighted criteria and grade bands; revisions copy criteria with approved changes and publishing requires production-ready criteria and grade bands.',
            'inputs' => [
                'Rating criteria',
                'Criterion weights',
                'Grade bands',
                'Industry variant',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.entrepreneurs.rating_framework',
                'entrepreneur.portal.plan_assessment',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => RatingFrameworkManager::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'entrepreneur.plan_assessment' => [
            'id' => 'entrepreneur.plan_assessment',
            'area' => 'Entrepreneurs',
            'name' => 'Entrepreneur Plan Assessment',
            'summary' => 'Scores business plans against the published rating framework and derives the overall grade.',
            'formula' => 'Criterion score = max(35, min(82, 48 + keyword matches * 8 + min(18, floor(word count / 25)))) before advisor overrides; weighted score is sum(score * criterion weight) / 100 and grade is resolved by framework bands.',
            'inputs' => [
                'Business plan sections',
                'Published rating framework',
                'AI or advisor criterion scores',
                'Document support',
            ],
            'config_refs' => [],
            'where_used' => [
                'entrepreneur.portal.plan_assessment',
                'advisor.entrepreneurs.advisory_readiness',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => Assessment::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'entrepreneur.advisory_readiness' => [
            'id' => 'entrepreneur.advisory_readiness',
            'area' => 'Entrepreneurs',
            'name' => 'Entrepreneur Advisory Readiness',
            'summary' => 'Surfaces advisory readiness when the latest plan assessment reaches the advisory threshold.',
            'formula' => 'Weighted assessment score uses advisor scores where present, otherwise AI scores; a signal is created when score >= 75.',
            'inputs' => [
                'Latest plan assessment',
                'Framework criterion weights',
                'Advisor scores',
                'AI scores',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.entrepreneurs.advisory_readiness',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => AdvisoryReadiness::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'entrepreneur.readiness' => [
            'id' => 'entrepreneur.readiness',
            'area' => 'Entrepreneurs',
            'name' => 'Entrepreneur Readiness Assessment',
            'summary' => 'Scores founder readiness responses and routes the entrepreneur to the next journey stage.',
            'formula' => 'Numeric answers are clamped to 0-5, averaged, and scaled to 0-100; score >= 78 with no personal barriers is ready, score >= 45 is develop_first, otherwise not_yet.',
            'inputs' => [
                'Readiness assessment responses',
                'Personal barrier responses',
            ],
            'config_refs' => [],
            'where_used' => [
                'entrepreneur.portal.readiness',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => Readiness::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'entrepreneur.idea_validation' => [
            'id' => 'entrepreneur.idea_validation',
            'area' => 'Entrepreneurs',
            'name' => 'Entrepreneur Idea Validation',
            'summary' => 'Evaluates founder-supplied concept fields and produces viability alerts from thin or negative demand signals.',
            'formula' => 'Required idea fields with fewer than four words produce thin-field alerts; demand signals containing none produce a no-demand-signal alert alongside AI evaluation context.',
            'inputs' => [
                'Problem',
                'Target customer',
                'Solution',
                'Value proposition',
                'Demand signal',
                'Revenue model',
            ],
            'config_refs' => [],
            'where_used' => [
                'entrepreneur.portal.idea_validation',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => IdeaValidationService::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'entrepreneur.revision_progress' => [
            'id' => 'entrepreneur.revision_progress',
            'area' => 'Entrepreneurs',
            'name' => 'Entrepreneur Revision Progress',
            'summary' => 'Compares assessment rounds and calculates plan revision trajectory.',
            'formula' => 'Overall delta = current weighted score - previous weighted score; trajectory percent = (current - previous) / (100 - previous) * 100 when improvement headroom exists, otherwise 0.',
            'inputs' => [
                'Previous plan assessment',
                'Current plan assessment',
                'Rating framework criterion weights',
            ],
            'config_refs' => [],
            'where_used' => [
                'entrepreneur.portal.revision_progress',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => Revision::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'wellbeing.trend' => [
            'id' => 'wellbeing.trend',
            'area' => 'Wellbeing',
            'name' => 'Wellbeing Trend Analytics',
            'summary' => 'Aggregates wellbeing check-ins into dashboard summary and monthly trend rows.',
            'formula' => 'Summary averages business confidence and personal coping, counts low personal coping check-ins, and calculates current completion rate as respondents / prompt population.',
            'inputs' => [
                'Wellbeing check-ins',
                'Coaching signals',
                'Prompt population',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.wellbeing.trends',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => WellbeingTrendAnalytics::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'wellbeing.low_coping_signal' => [
            'id' => 'wellbeing.low_coping_signal',
            'area' => 'Wellbeing',
            'name' => 'Low Personal Coping Signal',
            'summary' => 'Detects an internal coaching signal after repeated low personal coping check-ins.',
            'formula' => 'A signal is created when current personal coping <= 2 and the previous monthly check-in for the same client/user also has personal coping <= 2.',
            'inputs' => [
                'Current wellbeing check-in',
                'Previous monthly wellbeing check-in',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.wellbeing.signals',
                'advisor.coach.referrals',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => CoachingSignalDetector::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'coach.signal_mapping' => [
            'id' => 'coach.signal_mapping',
            'area' => 'Coach panel',
            'name' => 'Coach Signal Mapping',
            'summary' => 'Maps raw coaching signals to advisor-reviewed coach referral suggestions.',
            'formula' => 'Signal type maps to a specialisation, threshold reference, and rationale; suggestions always require advisor final decision and never auto-refer.',
            'inputs' => [
                'Coaching signal type',
                'Signal severity',
                'Signal evidence',
            ],
            'config_refs' => [],
            'where_used' => [
                'advisor.coach.referrals',
            ],
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => CoachSignalDetector::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],

        'payments.retry_policy' => [
            'id' => 'payments.retry_policy',
            'area' => 'Payments',
            'name' => 'Payment Retry Policy',
            'summary' => 'Controls how failed scheduled payments become retryable and how long the platform waits before retry attempts.',
            'formula' => 'Retry availability is bounded by the configured maximum attempts and retry delay interval; manual retries bypass the automatic max-attempt loop.',
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
            'sources' => [
                'PLAN-METHODOLOGY-REGISTRY.md',
            ],
            'owning_service' => PaymentProcessor::class,
            'version' => '2026-05-wo-m02',
            'internal_only' => true,
        ],
    ],
];
