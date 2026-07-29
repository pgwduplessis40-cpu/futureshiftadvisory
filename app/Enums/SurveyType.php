<?php

declare(strict_types=1);

namespace App\Enums;

enum SurveyType: string
{
    case GeneralExperience = 'general_experience';
    case ServiceImprovement = 'service_improvement';

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

    public function label(): string
    {
        return match ($this) {
            self::GeneralExperience => 'General experience',
            self::ServiceImprovement => 'Service improvement',
        };
    }
}
