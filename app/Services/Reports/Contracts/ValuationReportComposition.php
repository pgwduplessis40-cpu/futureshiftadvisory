<?php

declare(strict_types=1);

namespace App\Services\Reports\Contracts;

use App\Models\Client;
use App\Models\Report;
use App\Models\User;

/**
 * Typed boundary for advisor-reviewed business valuation report composition.
 */
interface ValuationReportComposition
{
    public function compose(Client $client, ?User $actor = null): Report;
}
