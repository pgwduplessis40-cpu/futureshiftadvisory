<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * Typed earnings inputs used by the valuation normalisation worksheet.
 */
final readonly class ValuationEarningsNormalisation
{
    /** @param list<ValuationAdjustment> $addBacks */
    public function __construct(
        public ?float $reportedNetProfit,
        public ?float $normalisedEbitda,
        public ?float $sellerDiscretionaryEarnings,
        public array $addBacks,
    ) {}
}
