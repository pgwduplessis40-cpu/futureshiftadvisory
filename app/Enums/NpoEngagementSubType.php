<?php

declare(strict_types=1);

namespace App\Enums;

enum NpoEngagementSubType: string
{
    case StandardNpo = 'standard_npo';
    case SocialEnterprise = 'social_enterprise';
    case GovernanceReview = 'governance_review';

    public function label(): string
    {
        return match ($this) {
            self::StandardNpo => 'Standard NPO',
            self::SocialEnterprise => 'Social Enterprise',
            self::GovernanceReview => 'Governance Review',
        };
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }
}
