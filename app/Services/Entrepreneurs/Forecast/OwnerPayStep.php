<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class OwnerPayStep implements \JsonSerializable
{
    public function __construct(public string $effectiveOn, public ?string $weeklyAmount, public string $trigger, public ?string $threshold = null)
    {
        ForecastDates::read($effectiveOn);
        DriverNumbers::read($weeklyAmount);
        DriverNumbers::read($threshold);
        if (! in_array($trigger, ['base', 'trailing_three_month_average_revenue'], true) || ($trigger === 'base' && $threshold !== null)) {
            throw new \InvalidArgumentException('Owner pay needs an explicit base or trailing-three-month average revenue trigger.');
        }
    }

    /** @return array{effective_on:string,weekly_amount:?string,trigger:string,threshold:?string} */
    public function jsonSerialize(): array
    {
        return ['effective_on' => $this->effectiveOn, 'weekly_amount' => $this->weeklyAmount, 'trigger' => $this->trigger, 'threshold' => $this->threshold];
    }
}
