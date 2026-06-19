<?php

declare(strict_types=1);

namespace App\Enums;

enum SurveyQuestionType: string
{
    case Likert = 'likert';
    case Nps = 'nps';
    case Boolean = 'boolean';
    case AnchoredMatrix = 'anchored_matrix';

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
