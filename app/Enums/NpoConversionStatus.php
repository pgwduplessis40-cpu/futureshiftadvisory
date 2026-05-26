<?php

declare(strict_types=1);

namespace App\Enums;

enum NpoConversionStatus: string
{
    case ReportDelivered = 'report_delivered';
    case Declined = 'declined';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::ReportDelivered => 'Report Delivered',
            self::Declined => 'Declined',
            self::Converted => 'Converted',
        };
    }
}
