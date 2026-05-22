<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Enums\DiscountMethod;

final readonly class DiscountRateResult
{
    /**
     * @param  array<int, array{claim:string, source_reference:string}>  $sourceAttributions
     */
    public function __construct(
        public DiscountMethod $method,
        public float $rate,
        public string $rationale,
        public array $sourceAttributions,
    ) {}
}
