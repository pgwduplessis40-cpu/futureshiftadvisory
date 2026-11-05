<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

final class AccountingNumbers implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.assets_tax_actuals'];
    }

    public static function signed(?string $value): ?BigDecimal
    {
        $negative = $value !== null && str_starts_with($value, '-');
        $number = DriverNumbers::read($negative ? substr($value, 1) : $value);

        return $negative ? $number?->negated() : $number;
    }

    public static function money(?BigDecimal $value): ?string
    {
        return $value === null ? null : DriverNumbers::money($value);
    }

    public static function cents(?string $value): ?BigDecimal
    {
        $number = self::signed($value);

        return $number === null ? null : BigDecimal::of(DriverNumbers::money($number));
    }

    public static function same(?string $left, ?string $right): ?bool
    {
        return $left === null || $right === null ? null
            : DriverNumbers::money(BigDecimal::of($left)) === DriverNumbers::money(BigDecimal::of($right));
    }

    /** @param list<string> $missing
     * @param  list<string>  $errors
     */
    public static function status(array $missing, array $errors = []): string
    {
        return $errors !== [] ? 'fail' : ($missing !== [] ? 'cannot_verify' : 'pass');
    }
}
