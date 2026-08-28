<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;

/**
 * Resolved, ownership-checked sources for an entrepreneur assessment report.
 */
final readonly class EntrepreneurAssessmentInputs
{
    public function __construct(
        public PlanAssessment $assessment,
        public BusinessPlan $plan,
        public EntrepreneurProfile $profile,
    ) {}
}
