<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * A normalized valuation adjustment, safe for report rendering.
 */
final readonly class ValuationAdjustment
{
    public function __construct(
        public string $label,
        public float $amount,
        public string $rationale,
    ) {}
}
