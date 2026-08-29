<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

use App\Models\DdEngagement;
use App\Models\DdRiskRegisterItem;
use App\Models\DdValuation;
use Illuminate\Support\Collection;

/**
 * Resolved persisted sources for an acquisition Go/No-Go report.
 */
final readonly class AcquisitionGoNoGoReportInputs
{
    /** @param Collection<int, DdRiskRegisterItem> $risks */
    public function __construct(
        public DdEngagement $engagement,
        public ?DdValuation $valuation,
        public Collection $risks,
        public DueDiligenceRecommendation $recommendation,
        public AcquisitionWalkAwayPrice $price,
    ) {}
}
