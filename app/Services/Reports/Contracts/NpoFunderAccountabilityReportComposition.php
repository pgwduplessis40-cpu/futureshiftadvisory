<?php

declare(strict_types=1);

namespace App\Services\Reports\Contracts;

use App\Models\ClientFunderRecord;
use App\Models\NpoEngagement;
use App\Models\Report;
use App\Models\User;

/**
 * Typed boundary for Funder Accountability report composition.
 */
interface NpoFunderAccountabilityReportComposition
{
    public function compose(NpoEngagement $engagement, ?ClientFunderRecord $record = null, ?User $actor = null): Report;
}
