<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class AssetInput implements \JsonSerializable
{
    public function __construct(public string $id, public string $source, public ?string $cost, public ?string $classification,
        public ?string $policySource, public ?bool $reviewed, public ?string $paidOn, public ?string $recognitionStartOn,
        public ?int $lifeMonths, public ?string $residual, public ?string $method, public ?string $openingDepreciation = null)
    {
        if (trim($id) === '' || trim($source) === '' || ($classification !== null && ! in_array($classification, ['capital', 'expense'], true))) {
            throw new \InvalidArgumentException('An asset needs an identity, source and explicit capital/expense classification.');
        }
        if ($lifeMonths !== null && ($lifeMonths < 1 || $lifeMonths > 1200)) {
            throw new \InvalidArgumentException('Specify a useful life of 1-1200 months.');
        }
        foreach ([$cost, $residual, $openingDepreciation] as $value) {
            DriverNumbers::read($value);
        }
        ForecastDates::read($paidOn);
        ForecastDates::read($recognitionStartOn);
        if ($method !== null && $method !== 'straight_line_full_start_month') {
            throw new \InvalidArgumentException('The supplied depreciation method is not supported.');
        }
    }

    /** @return array{id:string,source:string,cost:?string,classification:?string,policy_source:?string,reviewed:?bool,paid_on:?string,recognition_start_on:?string,life_months:?int,residual:?string,method:?string,opening_depreciation:?string} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'source' => $this->source, 'cost' => $this->cost, 'classification' => $this->classification,
            'policy_source' => $this->policySource, 'reviewed' => $this->reviewed, 'paid_on' => $this->paidOn,
            'recognition_start_on' => $this->recognitionStartOn, 'life_months' => $this->lifeMonths, 'residual' => $this->residual,
            'method' => $this->method, 'opening_depreciation' => $this->openingDepreciation];
    }
}
