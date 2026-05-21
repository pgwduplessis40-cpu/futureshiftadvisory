<?php

declare(strict_types=1);

namespace App\Enums;

enum QuestionnaireSet: string
{
    case STANDARD_ADVISORY = 'standard_advisory';
    case DUE_DILIGENCE = 'dd_specific';
    case POST_ACQUISITION_GAP = 'post_acquisition_gap';
    case ENTREPRENEUR_READINESS = 'entrepreneur_readiness';
    case ENTREPRENEUR_IDEA_VALIDATION = 'entrepreneur_idea_validation';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $set): string => $set->value,
            self::cases(),
        );
    }
}
