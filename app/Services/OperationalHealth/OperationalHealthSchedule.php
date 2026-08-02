<?php

declare(strict_types=1);

namespace App\Services\OperationalHealth;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class OperationalHealthSchedule
{
    public const DEFAULT_WEEKDAY_TIMES = [
        '07:30',
        '08:30',
        '09:30',
        '10:30',
        '11:30',
        '12:30',
        '13:30',
        '14:30',
        '15:30',
        '16:30',
    ];

    public const DEFAULT_WEEKEND_TIMES = [
        '07:30',
    ];

    public function timezone(): string
    {
        $timezone = (string) config('operational_health.timezone', 'Pacific/Auckland');

        return in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : 'Pacific/Auckland';
    }

    /**
     * @return array<int, string>
     */
    public function weekdayTimes(): array
    {
        return $this->normaliseTimes(
            config('operational_health.weekday_times'),
            self::DEFAULT_WEEKDAY_TIMES,
        );
    }

    /**
     * @return array<int, string>
     */
    public function weekendTimes(): array
    {
        return $this->normaliseTimes(
            config('operational_health.weekend_times'),
            self::DEFAULT_WEEKEND_TIMES,
        );
    }

    /**
     * @return array<int, string>
     */
    public function timesFor(CarbonInterface $date): array
    {
        $localDate = Carbon::parse($date->toIso8601String())->timezone($this->timezone());

        return $localDate->isWeekend()
            ? $this->weekendTimes()
            : $this->weekdayTimes();
    }

    /**
     * @return array<int, string>
     */
    public function dueTimesFor(CarbonInterface $date): array
    {
        $localDate = Carbon::parse($date->toIso8601String())->timezone($this->timezone());

        return array_values(array_filter(
            $this->timesFor($localDate),
            fn (string $time): bool => $this->atTime($localDate, $time)->lessThanOrEqualTo($localDate),
        ));
    }

    public function nextRunAfter(?CarbonInterface $after = null): ?Carbon
    {
        $localDate = $after instanceof CarbonInterface
            ? Carbon::parse($after->toIso8601String())->timezone($this->timezone())
            : Carbon::now($this->timezone());

        foreach (range(0, 7) as $offset) {
            $candidateDay = $localDate->copy()->addDays($offset)->startOfDay();

            foreach ($this->timesFor($candidateDay) as $time) {
                $candidate = $this->atTime($candidateDay, $time);

                if ($candidate->greaterThan($localDate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function atTime(CarbonInterface $date, string $time): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return Carbon::parse($date->toDateString(), $this->timezone())
            ->setTime($hour, $minute);
    }

    /**
     * @param  array<int, string>  $fallback
     * @return array<int, string>
     */
    private function normaliseTimes(mixed $value, array $fallback): array
    {
        $items = is_array($value)
            ? $value
            : explode(',', (string) $value);

        $times = [];

        foreach ($items as $item) {
            $time = trim((string) $item);

            if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1) {
                $times[] = $time;
            }
        }

        $times = array_values(array_unique($times));
        sort($times);

        return $times !== [] ? $times : $fallback;
    }
}
