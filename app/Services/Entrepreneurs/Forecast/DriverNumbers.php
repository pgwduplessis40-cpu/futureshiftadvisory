<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class DriverNumbers implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.driver_revenue'];
    }

    public static function read(?string $value): ?BigDecimal
    {
        if ($value === null) {
            return null;
        }
        if (preg_match('/^\d{1,18}(?:\.\d{1,8})?$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Driver values must be non-negative decimal strings, with at most eight decimal places.');
        }

        return BigDecimal::of($value);
    }

    public static function money(BigDecimal $value): string
    {
        return (string) $value->toScale(2, RoundingMode::HalfUp);
    }
}
