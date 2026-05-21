<?php

declare(strict_types=1);

namespace App\Enums;

enum EntrepreneurStage: string
{
    case INVITED = 'invited';
    case ONBOARDING = 'onboarding';
    case READINESS = 'readiness';
    case IDEA_VALIDATION = 'idea_validation';
    case BUILDING_PHASE_1 = 'building_phase1';
    case BUILDING_PHASE_2 = 'building_phase2';
    case BUILDING_PHASE_3 = 'building_phase3';
    case BUILDING_PHASE_4 = 'building_phase4';
    case BUILDING_PHASE_5 = 'building_phase5';
    case SUBMITTED = 'submitted';
    case ASSESSMENT = 'assessment';
    case REVISING = 'revising';
    case LAUNCHED = 'launched';
    case ADVISORY_READY = 'advisory_ready';

    public function label(): string
    {
        return match ($this) {
            self::INVITED => 'Invited',
            self::ONBOARDING => 'Onboarding',
            self::READINESS => 'Readiness',
            self::IDEA_VALIDATION => 'Idea validation',
            self::BUILDING_PHASE_1 => 'Building phase 1',
            self::BUILDING_PHASE_2 => 'Building phase 2',
            self::BUILDING_PHASE_3 => 'Building phase 3',
            self::BUILDING_PHASE_4 => 'Building phase 4',
            self::BUILDING_PHASE_5 => 'Building phase 5',
            self::SUBMITTED => 'Submitted',
            self::ASSESSMENT => 'Assessment',
            self::REVISING => 'Revising',
            self::LAUNCHED => 'Launched',
            self::ADVISORY_READY => 'Advisory ready',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function phaseOneValues(): array
    {
        return [
            self::INVITED->value,
            self::ONBOARDING->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function activeCapacityValues(): array
    {
        return self::phaseOneValues();
    }
}
