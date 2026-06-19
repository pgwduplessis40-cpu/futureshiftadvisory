<?php

declare(strict_types=1);

namespace App\Enums;

enum SurveyAssignmentStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [
            self::Pending->value,
            self::InProgress->value,
        ];
    }
}
