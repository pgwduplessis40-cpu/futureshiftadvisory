<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;

final class ForecastDates implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.dated_cash'];
    }

    /** @return ($value is null ? null : \DateTimeImmutable) */
    public static function read(?string $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        if (! $date || $date->format('Y-m-d') !== $value || preg_match('/^[1-9]\d{3}-\d{2}-\d{2}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Cash dates must be real YYYY-MM-DD calendar dates.');
        }

        return $date;
    }

    /** @return ($date is null ? null : ($days is null ? null : string)) */
    public static function shift(?string $date, ?int $days): ?string
    {
        $value = self::read($date);
        if ($days !== null && abs($days) > 36500) {
            throw new \InvalidArgumentException('Cash timing must be within 36500 calendar days.');
        }

        return $value !== null && $days !== null ? $value->modify(sprintf('%+d days', $days))->format('Y-m-d') : null;
    }

    public static function end(ForecastCalendar $calendar): string
    {
        return (new \DateTimeImmutable($calendar->periods[array_key_last($calendar->periods)].'-01', new \DateTimeZone('UTC')))->format('Y-m-t');
    }

    /** @return list<string> */
    public static function recur(string $first, string $last, string $frequency): array
    {
        $anchor = self::read($first);
        self::read($last);
        if ($first > $last || ! in_array($frequency, ['once', 'weekly', 'monthly', 'quarterly', 'annual'], true)) {
            throw new \InvalidArgumentException('Specify an ordered recurrence and a supported frequency.');
        }
        $dates = [];
        for ($index = 0; $index < 3200; $index++) {
            if ($frequency === 'weekly') {
                $next = $anchor->modify('+'.($index * 7).' days');
            } else {
                $months = $index * match ($frequency) {
                    'quarterly' => 3, 'annual' => 12, default => 1
                };
                $month = $anchor->modify('first day of this month')->modify('+'.$months.' months');
                $next = $month->setDate((int) $month->format('Y'), (int) $month->format('m'), min((int) $anchor->format('d'), (int) $month->format('t')));
            }
            if ($next->format('Y-m-d') > $last) {
                return $dates;
            }
            $dates[] = $next->format('Y-m-d');
            if ($frequency === 'once') {
                return $dates;
            }
        }
        throw new \InvalidArgumentException('The payment schedule exceeds 3200 occurrences.');
    }
}
