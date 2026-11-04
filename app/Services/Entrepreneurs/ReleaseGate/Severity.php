<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

enum Severity: string
{
    case Block = 'block';
    case Review = 'review';
    case Info = 'info';
    case Autofix = 'autofix';

    public function rank(): int
    {
        return match ($this) {
            self::Info => 0, self::Autofix => 1, self::Review => 2, self::Block => 3,
        };
    }
}
