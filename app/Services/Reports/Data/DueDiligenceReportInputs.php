<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

use App\Models\AnalysisFinding;
use App\Models\DdEngagement;
use App\Models\DdIntegrationPlanItem;
use App\Models\DdRiskRegisterItem;
use App\Models\DdValuation;
use Illuminate\Support\Collection;

/**
 * Resolved persisted sources for a standalone due-diligence report.
 */
final readonly class DueDiligenceReportInputs
{
    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @param  Collection<int, DdIntegrationPlanItem>  $integrationPlan
     */
    public function __construct(
        public DdEngagement $engagement,
        public Collection $findings,
        public ?DdValuation $valuation,
        public Collection $risks,
        public Collection $integrationPlan,
        public DueDiligenceRecommendation $recommendation,
    ) {}
}
