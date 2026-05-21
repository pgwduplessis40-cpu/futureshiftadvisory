<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case SUSPENDED = 'suspended';
    case OFFBOARDED = 'offboarded';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::SUSPENDED => 'Suspended',
            self::OFFBOARDED => 'Offboarded',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases(),
        );
    }
}
