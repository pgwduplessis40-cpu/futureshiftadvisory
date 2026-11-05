<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class TaxPolicy implements \JsonSerializable
{
    public function __construct(public ?string $entityBasis, public ?string $ratePercent, public ?bool $lossesEligible,
        public ?string $policySource, public ?string $eligibilitySource, public ?string $version)
    {
        if (DriverNumbers::read($ratePercent)?->isGreaterThan(100)) {
            throw new \InvalidArgumentException('The supplied planning tax rate cannot exceed 100 percent.');
        }
    }

    /** @return array{entity_basis:?string,rate_percent:?string,losses_eligible:?bool,policy_source:?string,eligibility_source:?string,version:?string} */
    public function jsonSerialize(): array
    {
        return ['entity_basis' => $this->entityBasis, 'rate_percent' => $this->ratePercent, 'losses_eligible' => $this->lossesEligible,
            'policy_source' => $this->policySource, 'eligibility_source' => $this->eligibilitySource, 'version' => $this->version];
    }
}
