<?php

declare(strict_types=1);

namespace App\Enums;

enum PvType: string
{
    case BusinessValuation = 'business_valuation';
    case ImprovementOpportunity = 'improvement_opportunity';
    case RiskCost = 'risk_cost';
    case GoalTarget = 'goal_target';
    case MilestoneImpact = 'milestone_impact';

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
