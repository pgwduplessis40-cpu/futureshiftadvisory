<?php

declare(strict_types=1);

namespace App\Services\Reports\Contracts;

use App\Models\DdEngagement;
use App\Models\Report;
use App\Models\User;

/**
 * Typed boundary for advisor-reviewed due-diligence report composition.
 */
interface DueDiligenceReportComposition
{
    public function compose(DdEngagement $engagement, ?User $actor = null): Report;
}
