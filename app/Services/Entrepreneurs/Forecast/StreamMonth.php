<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class StreamMonth implements \JsonSerializable
{
    public function __construct(
        public ?string $bookedUnits,
        public ?string $deliveryUnits,
        public ?string $invoicedUnits,
        public ?string $founderHours,
        public ?string $additionalCapacityUnits,
        public ?string $provider = null,
        public ?string $providerUnitCost = null,
        public ?string $noCostBasis = null,
    ) {
        foreach ([$bookedUnits, $deliveryUnits, $invoicedUnits, $founderHours, $additionalCapacityUnits, $providerUnitCost] as $value) {
            DriverNumbers::read($value);
        }
    }

    /** @return array{booked_units:?string,delivery_units:?string,invoiced_units:?string,founder_hours:?string,additional_capacity_units:?string,provider:?string,provider_unit_cost:?string,no_cost_basis:?string} */
    public function jsonSerialize(): array
    {
        return ['booked_units' => $this->bookedUnits, 'delivery_units' => $this->deliveryUnits, 'invoiced_units' => $this->invoicedUnits,
            'founder_hours' => $this->founderHours, 'additional_capacity_units' => $this->additionalCapacityUnits,
            'provider' => $this->provider, 'provider_unit_cost' => $this->providerUnitCost, 'no_cost_basis' => $this->noCostBasis];
    }
}
