<?php

declare(strict_types=1);

namespace App\Enums;

enum NpoTiritiMode: string
{
    case Standalone = 'standalone';
    case Woven = 'woven';

    public function label(): string
    {
        return match ($this) {
            self::Standalone => 'Mode A - Standalone Te Tiriti',
            self::Woven => 'Mode B - Woven Te Tiriti',
        };
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ],
            self::cases(),
        );
    }
}
