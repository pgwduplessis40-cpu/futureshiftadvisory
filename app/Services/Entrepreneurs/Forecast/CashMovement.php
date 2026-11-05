<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class CashMovement implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $source,
        public ?string $date,
        public ?string $amount,
        public string $direction,
        public string $category,
        public int $sequence,
        public ?bool $verified = null,
    ) {
        if (trim($id) === '' || trim($source) === '' || $sequence < 0) {
            throw new \InvalidArgumentException('Each movement needs an identity, source and non-negative date sequence.');
        }
        $directions = match ($category) {
            'operating_receipt', 'funding' => ['in'],
            'operating_payment', 'owner_pay', 'capex', 'debt_principal', 'distribution', 'interest', 'fees' => ['out'],
            'tax', 'gst' => ['in', 'out'],
            default => [],
        };
        if (! in_array($direction, $directions, true)) {
            throw new \InvalidArgumentException('Cash direction must match a named movement category.');
        }
        ForecastDates::read($date);
        DriverNumbers::read($amount);
    }

    /** @return array{id:string,source:string,date:?string,amount:?string,direction:string,category:string,sequence:int,verified:?bool} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'source' => $this->source, 'date' => $this->date, 'amount' => $this->amount,
            'direction' => $this->direction, 'category' => $this->category, 'sequence' => $this->sequence, 'verified' => $this->verified];
    }
}
