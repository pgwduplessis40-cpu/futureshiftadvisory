<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Checks
{
    /** @param list<Fact> $facts */
    public static function result(string $id, bool $passes, string $message, array $facts = [], Severity $severity = Severity::Block, string $precision = '0.01'): Subcheck
    {
        return new Subcheck($id, $passes ? CheckStatus::Pass : CheckStatus::Fail, $severity, $message, $facts, precision: $precision);
    }

    public static function close(BigDecimal $left, BigDecimal $right, string $tolerance = '0.01'): bool
    {
        return $left->minus($right)->abs()->isLessThanOrEqualTo($tolerance);
    }

    public static function money(BigDecimal $amount, int $precision = 2): string
    {
        return (string) $amount->toScale($precision, RoundingMode::HalfUp);
    }

    /** @param list<Fact> $facts */
    public static function sum(array $facts): ?BigDecimal
    {
        $total = BigDecimal::of(0);
        foreach ($facts as $fact) {
            $number = $fact->number();
            if ($number === null) {
                return null;
            }
            $total = $total->plus($number);
        }

        return $total;
    }

    /** @param list<Fact> $facts */
    public static function currentClaims(array $facts): Subcheck
    {
        $confirmed = $facts !== [] && array_all($facts, fn (Fact $fact): bool => $fact->confirmed && $fact->sources !== []);

        return $confirmed
            ? self::result('current-claims', true, 'Current claim versions have source references.', $facts)
            : Subcheck::missing('current-claims', 'confirmed current claim versions and source locations', compared: $facts);
    }
}
