<?php

declare(strict_types=1);

namespace App\Services\Reports\Contracts;

use App\Models\NpoEngagement;
use App\Models\Report;
use App\Models\User;

/**
 * Typed boundary for NPO health and advisor report composition.
 */
interface NpoHealthReportComposition
{
    public function composeHealth(NpoEngagement $engagement, ?User $actor = null): Report;

    public function composeAdvisor(NpoEngagement $engagement, ?User $actor = null): Report;
}
