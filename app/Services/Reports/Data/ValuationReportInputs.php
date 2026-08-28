<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\FinancialSnapshot;

/**
 * Resolved sources for a standalone valuation report.
 */
final readonly class ValuationReportInputs
{
    public function __construct(
        public Client $client,
        public ?BusinessValuation $valuation,
        public ?FinancialSnapshot $financialSnapshot,
    ) {}
}
