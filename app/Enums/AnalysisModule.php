<?php

declare(strict_types=1);

namespace App\Enums;

enum AnalysisModule: string
{
    case Financial = 'financial';
    case WebsiteAudit = 'website_audit';
    case Competitor = 'competitor';
    case Swot = 'swot';
    case Hr = 'hr';
    case Operational = 'operational';
    case Systems = 'systems';
    case Compliance = 'compliance';
    case RegulatoryImpact = 'regulatory_impact';
    case InsuranceRisk = 'insurance_risk';
    case KnowledgeAssessment = 'knowledge_assessment';
    case Scenario = 'scenario';
    case Succession = 'succession';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $module): string => $module->value,
            self::cases(),
        );
    }
}
