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

    /**
     * A calendar month label, as the forecast calendar states its start.
     *
     * Distinct from a date: a start period names a month, and accepting a day
     * alongside it would let two starts differing only in their day read as
     * different periods on a model that has no notion of one.
     */
    public static function month(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D', $value) === 1;
    }

    /**
     * A forecast period label, as every monthly producer writes it.
     *
     * A claim recorded against any other period is one no rule can read. It
     * would report the model figure missing while the advisor saw their claim
     * recorded against the month they meant, which is the failure the runway
     * basis guard exists to prevent.
     */
    public static function period(string $value): bool
    {
        return preg_match('/^month:(?:[1-9]|[1-9]\d{1,2})$/D', $value) === 1;
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
