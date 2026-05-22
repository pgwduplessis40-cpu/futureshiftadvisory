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

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Client Report',
            self::Advisor => 'Advisor Report',
            self::Stakeholder => 'Stakeholder Report',
            self::Trajectory => 'Business Health Trajectory Report',
            self::DueDiligence => 'Due Diligence Report',
            self::EntrepreneurAssessment => 'Entrepreneur Assessment Report',
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
