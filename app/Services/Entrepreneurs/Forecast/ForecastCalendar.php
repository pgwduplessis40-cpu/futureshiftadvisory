<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;

final readonly class ForecastCalendar implements \JsonSerializable, ProvidesMethodology
{
    /** @var list<string> */
    public array $periods;

    public function __construct(public string $startMonth, public int $months = 36)
    {
        if (preg_match('/^[1-9]\d{3}-(0[1-9]|1[0-2])$/D', $startMonth) !== 1 || $months < 1 || $months > 60) {
            throw new \InvalidArgumentException('Specify a calendar start month and a horizon of 1-60 months.');
        }
        $date = new \DateTimeImmutable($startMonth.'-01', new \DateTimeZone('UTC'));
        $periods = [];
        for ($index = 0; $index < $months; $index++) {
            $periods[] = $date->format('Y-m');
            $date = $date->modify('+1 month');
        }
        $this->periods = $periods;
    }

    public static function methodologyIds(): array
    {
        return ['entrepreneur.driver_revenue'];
    }

    /** @return array{start_month:string,months:int} */
    public function jsonSerialize(): array
    {
        return ['start_month' => $this->startMonth, 'months' => $this->months];
    }
}
