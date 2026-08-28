<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * Balance-sheet based valuation sanity-check inputs.
 */
final readonly class ValuationAssetFloor
{
    public function __construct(
        public ?float $assetFloorNzd,
        public ?float $cashOrSurplusAssetIndicatorNzd,
        public ?float $liabilitiesNzd,
        public string $sourceReference,
    ) {}
}
