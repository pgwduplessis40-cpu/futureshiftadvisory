<?php

declare(strict_types=1);

namespace App\Services\Reports\Contracts;

use App\Enums\ReportType;
use App\Models\Client;
use App\Models\Report;
use App\Models\User;

/**
 * Typed boundary for the four standard advisory report types.
 */
interface StandardAdvisoryReportComposition
{
    public function compose(Client $client, ReportType $type, ?User $actor = null): Report;
}
