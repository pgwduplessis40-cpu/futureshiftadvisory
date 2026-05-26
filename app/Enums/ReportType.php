<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportType: string
{
    case Client = 'client';
    case Advisor = 'advisor';
    case Stakeholder = 'stakeholder';
    case Trajectory = 'trajectory';
    case DueDiligence = 'due_diligence';
    case EntrepreneurAssessment = 'entrepreneur_assessment';
    case GovernanceReview = 'governance_review_report';
    case NpoHealth = 'npo_health_report';
    case NpoAdvisor = 'npo_advisor_report';
    case FunderAccountability = 'funder_accountability_report';
    case SocialEnterpriseDual = 'social_enterprise_dual_report';
    case ImpactSummary = 'impact_summary_report';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Client Report',
            self::Advisor => 'Advisor Report',
            self::Stakeholder => 'Stakeholder Report',
            self::Trajectory => 'Business Health Trajectory Report',
            self::DueDiligence => 'Due Diligence Report',
            self::EntrepreneurAssessment => 'Entrepreneur Assessment Report',
            self::GovernanceReview => 'Governance Review Report',
            self::NpoHealth => 'NPO Health Report',
            self::NpoAdvisor => 'NPO Advisor Report',
            self::FunderAccountability => 'Funder Accountability Report',
            self::SocialEnterpriseDual => 'Social Enterprise Dual Impact Report',
            self::ImpactSummary => 'Impact Summary Report',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
