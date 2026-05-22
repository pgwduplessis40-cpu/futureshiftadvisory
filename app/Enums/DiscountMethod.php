<?php

declare(strict_types=1);

namespace App\Enums;

enum DiscountMethod: string
{
    case OcrLinked = 'ocr_linked';
    case IndustryWacc = 'industry_wacc';
    case AdvisorConfigured = 'advisor_configured';
    case ClientInputted = 'client_inputted';

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
