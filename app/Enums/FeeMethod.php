<?php

declare(strict_types=1);

namespace App\Enums;

enum FeeMethod: string
{
    case HoursBased = 'hours_based';
    case OutcomeBased = 'outcome_based';
    case Entrepreneur = 'entrepreneur';
    case GovernanceReview = 'governance_review';
    case NpoRetainer = 'npo_retainer';

    case Integration = 'integration';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $method): string => $method->value,
            self::cases(),
        );
    }
}
