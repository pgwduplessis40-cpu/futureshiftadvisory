<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class RevenueStream implements \JsonSerializable
{
    /** @param array<string,StreamMonth> $schedule */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $unit,
        public ?string $price,
        public ?string $preparationHoursPerUnit,
        public ?string $deliveryHoursPerUnit,
        public ?bool $inBaseCase,
        public ?string $evidenceStatus,
        public array $schedule,
    ) {
        if (trim($id) === '' || trim($name) === '') {
            throw new \InvalidArgumentException('A revenue stream needs an identity and name.');
        }
        if ($evidenceStatus !== null && ! in_array($evidenceStatus, ['verified', 'own_record', 'founder_stated', 'founder_assumption'], true)) {
            throw new \InvalidArgumentException('Unknown revenue evidence status.');
        }
        foreach ([$price, $preparationHoursPerUnit, $deliveryHoursPerUnit] as $value) {
            DriverNumbers::read($value);
        }
    }

    /** @return array{id:string,name:string,unit:?string,price:?string,preparation_hours_per_unit:?string,delivery_hours_per_unit:?string,in_base_case:?bool,evidence_status:?string,schedule:array<string,StreamMonth>} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'unit' => $this->unit, 'price' => $this->price,
            'preparation_hours_per_unit' => $this->preparationHoursPerUnit, 'delivery_hours_per_unit' => $this->deliveryHoursPerUnit,
            'in_base_case' => $this->inBaseCase, 'evidence_status' => $this->evidenceStatus, 'schedule' => $this->schedule];
    }
}
