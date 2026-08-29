<?php

declare(strict_types=1);

namespace App\Services\Reports\Contracts;

use App\Models\DdEngagement;
use App\Models\Report;
use App\Models\User;

/**
 * Typed boundary for acquisition Go/No-Go report composition.
 */
interface AcquisitionGoNoGoReportComposition
{
    public function compose(DdEngagement $engagement, ?User $actor = null): Report;
}
