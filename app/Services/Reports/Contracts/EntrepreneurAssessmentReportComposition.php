<?php

declare(strict_types=1);

namespace App\Services\Reports\Contracts;

use App\Models\PlanAssessment;
use App\Models\Report;
use App\Models\User;

/**
 * Typed boundary for entrepreneur assessment report composition.
 */
interface EntrepreneurAssessmentReportComposition
{
    public function compose(PlanAssessment $assessment, ?User $actor = null): Report;
}
