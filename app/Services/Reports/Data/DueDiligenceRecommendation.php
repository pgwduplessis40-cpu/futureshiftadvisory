<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * Advisor-review recommendation derived from DD risk and valuation signals.
 */
final readonly class DueDiligenceRecommendation
{
    public function __construct(
        public string $decision,
        public string $rationale,
    ) {}
}
