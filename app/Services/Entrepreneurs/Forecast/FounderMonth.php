<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class FounderMonth implements \JsonSerializable
{
    public function __construct(
        public ?string $availableHours,
        public ?string $administrationHours,
        public ?string $businessDevelopmentHours,
    ) {
        foreach ([$availableHours, $administrationHours, $businessDevelopmentHours] as $value) {
            DriverNumbers::read($value);
        }
    }

    /** @return array{available_hours:?string,administration_hours:?string,business_development_hours:?string} */
    public function jsonSerialize(): array
    {
        return ['available_hours' => $this->availableHours, 'administration_hours' => $this->administrationHours,
            'business_development_hours' => $this->businessDevelopmentHours];
    }
}
