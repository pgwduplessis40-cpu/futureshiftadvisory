<?php

declare(strict_types=1);

namespace App\Enums;

enum AnalysisLens: string
{
    case Descriptive = 'descriptive';
    case Diagnostic = 'diagnostic';
    case Predictive = 'predictive';
    case Prescriptive = 'prescriptive';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $lens): string => $lens->value,
            self::cases(),
        );
    }
}
