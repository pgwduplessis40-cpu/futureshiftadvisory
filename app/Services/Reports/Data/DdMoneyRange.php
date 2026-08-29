<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * A normalized NZD valuation range used by due-diligence report contracts.
 */
final readonly class DdMoneyRange
{
    public function __construct(
        public float $low,
        public float $mid,
        public float $high,
    ) {}

    public function adjusted(float $amount): self
    {
        return new self(
            low: round(max(0, $this->low + $amount), 2),
            mid: round(max(0, $this->mid + $amount), 2),
            high: round(max(0, $this->high + $amount), 2),
        );
    }
}
