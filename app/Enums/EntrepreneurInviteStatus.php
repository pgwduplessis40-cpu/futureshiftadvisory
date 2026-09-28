<?php

declare(strict_types=1);

namespace App\Enums;

enum EntrepreneurInviteStatus: string
{
    case PENDING = 'pending';
    case EXPIRED = 'expired';
    case ACCEPTED = 'accepted';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::EXPIRED => 'Expired',
            self::ACCEPTED => 'Accepted',
            self::CANCELLED => 'Cancelled',
        };
    }
}
