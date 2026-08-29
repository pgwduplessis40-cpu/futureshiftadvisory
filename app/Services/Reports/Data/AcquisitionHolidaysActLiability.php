<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * Quantified Holidays Act exposure used in the acquisition price envelope.
 */
final readonly class AcquisitionHolidaysActLiability
{
    public function __construct(
        public float $amountNzd,
        public string $basis,
    ) {}
}
