<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ImprovementOpportunity;
use App\Models\SuccessionPlan;
use Illuminate\Support\Collection;

/**
 * Resolved sources for a standalone succession value-gap report.
 */
final readonly class SuccessionValueGapInputs
{
    /**
     * @param  Collection<int, ImprovementOpportunity>  $improvements
     */
    public function __construct(
        public Client $client,
        public ?BusinessValuation $valuation,
        public ?SuccessionPlan $successionPlan,
        public Collection $improvements,
    ) {}
}
