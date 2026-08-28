<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * Typed value-bridge measurements for a succession value-gap report.
 */
final readonly class SuccessionValueGapMetrics
{
    public function __construct(
        public ?float $currentValueNzd,
        public ?float $targetExitPvNzd,
        public ?float $currentGapNzd,
        public float $improvementPvNzd,
        public ?float $projectedValueNzd,
        public ?float $remainingGapNzd,
    ) {}
}
