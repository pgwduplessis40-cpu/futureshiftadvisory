<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use Brick\Math\Exception\RoundingNecessaryException;

final class FactValidation
{
    public static function date(mixed $value): bool
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m) !== 1) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    public static function horizon(?Fact $fact): ?int
    {
        $value = $fact?->number();
        if ($value === null || $value->isLessThan(1) || $value->isGreaterThan(600)) {
            return null;
        }
        try {
            return $value->toInt();
        } catch (RoundingNecessaryException) {
            return null;
        }
    }

    /** @param list<Fact> $rows */
    public static function monthlyCoverage(array $rows, ?int $horizon): bool
    {
        if ($horizon === null || count($rows) !== $horizon) {
            return false;
        }
        $periods = array_column(array_map(fn (Fact $f): array => ['period' => $f->period], $rows), 'period');

        return count(array_unique($periods)) === $horizon && array_all(range(1, $horizon), fn (int $m): bool => in_array('month:'.$m, $periods, true));
    }
}
