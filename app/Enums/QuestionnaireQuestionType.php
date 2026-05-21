<?php

declare(strict_types=1);

namespace App\Enums;

enum QuestionnaireQuestionType: string
{
    case TEXT = 'text';
    case LONG_TEXT = 'long-text';
    case NUMBER = 'number';
    case CURRENCY = 'currency';
    case DATE = 'date';
    case SINGLE_SELECT = 'single-select';
    case MULTI_SELECT = 'multi-select';
    case FILE_ATTACH = 'file-attach';
    case LIKERT = 'likert';

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
