<?php

declare(strict_types=1);

namespace App\Services\Reports\Contracts;

use App\Models\NpoEngagement;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\Data\NpoImpactSummaryInput;

/**
 * Typed boundary for client-authored NPO Impact Summary report composition.
 */
interface NpoImpactSummaryReportComposition
{
    public function compose(NpoEngagement $engagement, NpoImpactSummaryInput $input, ?User $actor = null): Report;
}
