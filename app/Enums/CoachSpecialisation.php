<?php

declare(strict_types=1);

namespace App\Enums;

enum CoachSpecialisation: string
{
    case LIFE = 'life';
    case BUSINESS_EXECUTIVE = 'business_executive';
    case MENTAL_HEALTH_WELLBEING = 'mental_health_wellbeing';
    case FINANCIAL_WELLNESS = 'financial_wellness';
    case CAREER = 'career';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $specialisation): string => $specialisation->value,
            self::cases(),
        );
    }
}
