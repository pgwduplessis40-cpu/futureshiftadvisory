<?php

declare(strict_types=1);

namespace App\Enums;

enum FindingSeverity: string
{
    case Info = 'info';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $severity): string => $severity->value,
            self::cases(),
        );
    }
}
