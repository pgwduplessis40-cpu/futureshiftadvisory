<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Client;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class PublicHolidayCalendar
{
    public const SOURCE_URL = 'https://www.govt.nz/browse/work/public-holidays-and-work/public-holidays-and-anniversary-dates/';

    /**
     * Te Ture mo te Hararei Tumatanui o te Kahui o Matariki 2022, schedule 1.
     *
     * @var array<int, string>
     */
    private const MATARIKI_DATES = [
        2022 => '2022-06-24',
        2023 => '2023-07-14',
        2024 => '2024-06-28',
        2025 => '2025-06-20',
        2026 => '2026-07-10',
        2027 => '2027-06-25',
        2028 => '2028-07-14',
        2029 => '2029-07-06',
        2030 => '2030-06-21',
        2031 => '2031-07-11',
        2032 => '2032-07-02',
        2033 => '2033-06-24',
        2034 => '2034-07-07',
        2035 => '2035-06-29',
        2036 => '2036-07-18',
        2037 => '2037-07-10',
        2038 => '2038-06-25',
        2039 => '2039-07-15',
        2040 => '2040-07-06',
        2041 => '2041-07-19',
        2042 => '2042-07-11',
        2043 => '2043-07-03',
        2044 => '2044-06-24',
        2045 => '2045-07-07',
        2046 => '2046-06-29',
        2047 => '2047-07-19',
        2048 => '2048-07-03',
        2049 => '2049-06-25',
        2050 => '2050-07-15',
        2051 => '2051-06-30',
        2052 => '2052-06-21',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function eventsBetween(mixed $start, mixed $end, array $regions = []): array
    {
        $startDate = $this->carbon($start)->startOfDay();
        $endDate = $this->carbon($end)->endOfDay();

        if ($endDate->lt($startDate)) {
            return [];
        }

        $events = [];
        foreach (range($startDate->year - 1, $endDate->year + 1) as $year) {
            array_push(
                $events,
                ...$this->nationalEvents($year),
                ...$this->regionalEvents($year, $regions),
            );
        }

        $seen = [];

        return collect($events)
            ->filter(fn (array $event): bool => $event['date']->betweenIncluded($startDate, $endDate))
            ->sortBy(fn (array $event): string => $event['date']->toDateString().':'.$event['id'])
            ->filter(function (array $event) use (&$seen): bool {
                $id = (string) $event['id'];

                if (isset($seen[$id])) {
                    return false;
                }

                $seen[$id] = true;

                return true;
            })
            ->map(fn (array $event): array => $this->payload($event))
            ->values()
            ->all();
    }

    public function holidayOn(mixed $date, array $regions = []): ?array
    {
        $target = $this->carbon($date)->startOfDay();

        return $this->eventsBetween($target, $target, $regions)[0] ?? null;
    }

    public function isPublicHoliday(mixed $date, array $regions = []): bool
    {
        return $this->holidayOn($date, $regions) !== null;
    }

    public function nextAvailableDate(mixed $date, array $regions = []): Carbon
    {
        $next = $this->carbon($date)->startOfDay();

        while ($this->isPublicHoliday($next, $regions)) {
            $next->addDay();
        }

        return $next;
    }

    public function validationMessage(array $holiday, string $subject): string
    {
        $title = (string) ($holiday['title'] ?? 'a public holiday');
        $scope = (string) ($holiday['scope'] ?? 'national');
        $region = trim((string) ($holiday['region'] ?? ''));
        $suffix = $scope === 'regional' && $region !== '' ? " ({$region})" : '';

        return "{$subject} cannot be scheduled on {$title}{$suffix}. Choose another date for this client.";
    }

    /**
     * @return array<int, string>
     */
    public function regionsForClient(Client $client): array
    {
        $address = is_array($client->address) ? $client->address : [];

        return collect([
            $address['region'] ?? null,
            $address['province'] ?? null,
            $address['state'] ?? null,
            $address['city'] ?? null,
            $address['locality'] ?? null,
            $address['territorial_authority'] ?? null,
        ])
            ->map(fn (mixed $value): string => trim((string) ($value ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function nationalEvents(int $year): array
    {
        $events = [
            ...$this->actualAndObservedEvents("national:new-years-day:{$year}", "New Year's Day", $this->date($year, 1, 1), $this->observedPaired($year, 1, 1)),
            ...$this->actualAndObservedEvents("national:day-after-new-years-day:{$year}", "Day after New Year's Day", $this->date($year, 1, 2), $this->observedPaired($year, 1, 2)),
            ...$this->actualAndObservedEvents("national:waitangi-day:{$year}", 'Waitangi Day', $this->date($year, 2, 6), $this->observedSingle($year, 2, 6)),
            $this->event("national:good-friday:{$year}", 'Good Friday', $this->easterSunday($year)->subDays(2), 'national'),
            $this->event("national:easter-monday:{$year}", 'Easter Monday', $this->easterSunday($year)->addDay(), 'national'),
            ...$this->actualAndObservedEvents("national:anzac-day:{$year}", 'Anzac Day', $this->date($year, 4, 25), $this->observedSingle($year, 4, 25)),
            $this->event("national:kings-birthday:{$year}", "King's Birthday", $this->nthWeekday($year, 6, 1, 1), 'national'),
            $this->event("national:labour-day:{$year}", 'Labour Day', $this->nthWeekday($year, 10, 1, 4), 'national'),
            ...$this->actualAndObservedEvents("national:christmas-day:{$year}", 'Christmas Day', $this->date($year, 12, 25), $this->observedPaired($year, 12, 25)),
            ...$this->actualAndObservedEvents("national:boxing-day:{$year}", 'Boxing Day', $this->date($year, 12, 26), $this->observedPaired($year, 12, 26)),
        ];

        if (isset(self::MATARIKI_DATES[$year])) {
            $events[] = $this->event("national:matariki:{$year}", 'Matariki', Carbon::parse(self::MATARIKI_DATES[$year]), 'national');
        }

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function regionalEvents(int $year, array $regions): array
    {
        return collect($this->regionalGroups($regions))
            ->map(fn (string $group): array => match ($group) {
                'auckland' => $this->event("regional:auckland:{$year}", 'Auckland Anniversary Day', $this->nearestMonday($year, 1, 29), 'regional', 'Auckland, Waikato, Bay of Plenty, Northland, Gisborne'),
                'canterbury_south' => $this->event("regional:canterbury-south:{$year}", 'Canterbury South Anniversary Day', $this->nthWeekday($year, 9, 1, 4), 'regional', 'Canterbury South'),
                'canterbury' => $this->event("regional:canterbury:{$year}", 'Canterbury Anniversary Day', $this->firstWeekday($year, 11, 2)->addDays(10), 'regional', 'Canterbury'),
                'chatham_islands' => $this->event("regional:chatham-islands:{$year}", 'Chatham Islands Anniversary Day', $this->nearestMonday($year, 11, 30), 'regional', 'Chatham Islands'),
                'hawkes_bay' => $this->event("regional:hawkes-bay:{$year}", "Hawke's Bay Anniversary Day", $this->nthWeekday($year, 10, 1, 4)->subDays(3), 'regional', "Hawke's Bay"),
                'marlborough' => $this->event("regional:marlborough:{$year}", 'Marlborough Anniversary Day', $this->nthWeekday($year, 10, 1, 4)->addDays(7), 'regional', 'Marlborough'),
                'nelson' => $this->event("regional:nelson:{$year}", 'Nelson Anniversary Day', $this->nearestMonday($year, 2, 1), 'regional', 'Nelson, Tasman, Buller'),
                'otago' => $this->event("regional:otago:{$year}", 'Otago Anniversary Day', $this->nearestMonday($year, 3, 23), 'regional', 'Otago'),
                'southland' => $this->event("regional:southland:{$year}", 'Southland Anniversary Day', $this->easterSunday($year)->addDays(2), 'regional', 'Southland'),
                'taranaki' => $this->event("regional:taranaki:{$year}", 'Taranaki Anniversary Day', $this->nthWeekday($year, 3, 1, 2), 'regional', 'Taranaki'),
                'wellington' => $this->event("regional:wellington:{$year}", 'Wellington Anniversary Day', $this->nearestMonday($year, 1, 22), 'regional', 'Wellington, Manawatu, Whanganui'),
                'westland' => $this->event("regional:westland:{$year}", 'Westland Anniversary Day', $this->nearestMonday($year, 12, 1), 'regional', 'Westland'),
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function regionalGroups(array $regions): array
    {
        return collect($regions)
            ->map(fn (mixed $region): ?string => $this->regionalGroupFor((string) $region))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function regionalGroupFor(string $region): ?string
    {
        $normalised = Str::of($region)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->toString();

        if ($normalised === '' || str_contains($normalised, 'north island') || str_contains($normalised, 'south island')) {
            return null;
        }

        if (str_contains($normalised, 'south canterbury') || str_contains($normalised, 'canterbury south')) {
            return 'canterbury_south';
        }

        return match (true) {
            str_contains($normalised, 'auckland'),
            str_contains($normalised, 'waikato'),
            str_contains($normalised, 'bay of plenty'),
            str_contains($normalised, 'northland'),
            str_contains($normalised, 'gisborne') => 'auckland',
            str_contains($normalised, 'canterbury'),
            str_contains($normalised, 'christchurch') => 'canterbury',
            str_contains($normalised, 'chatham') => 'chatham_islands',
            str_contains($normalised, 'hawke') => 'hawkes_bay',
            str_contains($normalised, 'marlborough') => 'marlborough',
            str_contains($normalised, 'nelson'),
            str_contains($normalised, 'tasman'),
            str_contains($normalised, 'buller') => 'nelson',
            str_contains($normalised, 'otago'),
            str_contains($normalised, 'dunedin') => 'otago',
            str_contains($normalised, 'southland'),
            str_contains($normalised, 'invercargill') => 'southland',
            str_contains($normalised, 'taranaki') => 'taranaki',
            str_contains($normalised, 'wellington'),
            str_contains($normalised, 'manawatu'),
            str_contains($normalised, 'whanganui') => 'wellington',
            str_contains($normalised, 'westland') => 'westland',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $id, string $title, Carbon $date, string $scope, ?string $region = null): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'date' => $date->copy()->startOfDay(),
            'scope' => $scope,
            'region' => $region,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function actualAndObservedEvents(string $id, string $title, Carbon $actual, Carbon $observed): array
    {
        $events = [
            $this->event("{$id}:actual", $title, $actual, 'national'),
        ];

        if (! $actual->isSameDay($observed)) {
            $events[] = $this->event("{$id}:observed", "{$title} (observed)", $observed, 'national');
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function payload(array $event): array
    {
        /** @var Carbon $date */
        $date = $event['date'];
        $scope = (string) $event['scope'];

        return [
            'id' => $event['id'],
            'title' => $event['title'],
            'starts_at' => $date->toIso8601String(),
            'kind' => 'public_holiday',
            'kind_label' => 'Public holiday',
            'status' => $scope === 'national' ? 'National' : 'Regional',
            'description' => $scope === 'national' ? 'New Zealand public holiday' : 'Regional anniversary day',
            'href' => null,
            'all_day' => true,
            'scope' => $scope,
            'region' => $event['region'],
            'source_url' => self::SOURCE_URL,
        ];
    }

    private function observedSingle(int $year, int $month, int $day): Carbon
    {
        $date = $this->date($year, $month, $day);

        return match ($date->dayOfWeek) {
            6 => $date->addDays(2),
            0 => $date->addDay(),
            default => $date,
        };
    }

    private function observedPaired(int $year, int $month, int $day): Carbon
    {
        $date = $this->date($year, $month, $day);

        return match ($date->dayOfWeek) {
            6, 0 => $date->addDays(2),
            default => $date,
        };
    }

    private function easterSunday(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return $this->date($year, $month, $day);
    }

    private function firstWeekday(int $year, int $month, int $weekday): Carbon
    {
        $date = $this->date($year, $month, 1);

        while ($date->dayOfWeek !== $weekday) {
            $date->addDay();
        }

        return $date;
    }

    private function nthWeekday(int $year, int $month, int $weekday, int $occurrence): Carbon
    {
        return $this->firstWeekday($year, $month, $weekday)->addWeeks($occurrence - 1);
    }

    private function nearestMonday(int $year, int $month, int $day): Carbon
    {
        $target = $this->date($year, $month, $day);
        $nearest = null;
        $nearestDistance = PHP_INT_MAX;

        for ($offset = -3; $offset <= 3; $offset++) {
            $candidate = $target->copy()->addDays($offset);

            if ($candidate->dayOfWeek !== 1) {
                continue;
            }

            $distance = abs($offset);
            if ($distance < $nearestDistance) {
                $nearest = $candidate;
                $nearestDistance = $distance;
            }
        }

        return $nearest ?? $target;
    }

    private function date(int $year, int $month, int $day): Carbon
    {
        return Carbon::create($year, $month, $day)->startOfDay();
    }

    private function carbon(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse((string) $value);
    }
}
