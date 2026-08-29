<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * One quantified negotiation adjustment used in an acquisition decision.
 */
final readonly class AcquisitionPriceChip
{
    public function __construct(
        public string $label,
        public float $amountNzd,
        public string $basis,
        public string $sourceReference,
    ) {}
}
