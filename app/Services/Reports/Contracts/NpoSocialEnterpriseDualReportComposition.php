<?php

declare(strict_types=1);

namespace App\Services\Reports\Contracts;

use App\Models\NpoEngagement;
use App\Models\Report;
use App\Models\User;

/**
 * Typed boundary for Social Enterprise Dual Impact report composition.
 */
interface NpoSocialEnterpriseDualReportComposition
{
    public function compose(NpoEngagement $engagement, ?User $actor = null): Report;
}
