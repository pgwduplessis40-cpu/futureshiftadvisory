<?php

declare(strict_types=1);

namespace App\Enums;

enum NpoSocialEnterpriseType: string
{
    case TradingEnterprise = 'trading_enterprise';
    case FeeForService = 'fee_for_service';
    case EmploymentPathway = 'employment_pathway';
    case CrossSubsidy = 'cross_subsidy';

    public function label(): string
    {
        return match ($this) {
            self::TradingEnterprise => 'Trading enterprise',
            self::FeeForService => 'Fee-for-service',
            self::EmploymentPathway => 'Employment pathway',
            self::CrossSubsidy => 'Cross-subsidy model',
        };
    }

    public function commercialWeight(): int
    {
        return match ($this) {
            self::TradingEnterprise => 60,
            self::FeeForService => 50,
            self::EmploymentPathway => 45,
            self::CrossSubsidy => 40,
        };
    }

    public function missionWeight(): int
    {
        return 100 - $this->commercialWeight();
    }

    /**
     * @return array<int, array{value:string, label:string, commercial_weight:int, mission_weight:int}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'commercial_weight' => $type->commercialWeight(),
                'mission_weight' => $type->missionWeight(),
            ],
            self::cases(),
        );
    }
}
