<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Pv\IndustryWaccRefresher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RefreshIndustryWacc extends Command
{
    protected $signature = 'industry-wacc:refresh
                            {--quarter= : Optional quarter label, for example 2026Q2.}
                            {--fetched-at= : Optional ISO-8601 fetch timestamp for deterministic runs.}';

    protected $description = 'Refresh NZ industry WACC reference data for PV discount-rate automation.';

    public function handle(IndustryWaccRefresher $refresher): int
    {
        $fetchedAtInput = $this->option('fetched-at');
        $fetchedAt = is_string($fetchedAtInput) && $fetchedAtInput !== ''
            ? Carbon::parse($fetchedAtInput)
            : null;

        $quarterInput = $this->option('quarter');
        $quarter = is_string($quarterInput) && $quarterInput !== ''
            ? strtoupper($quarterInput)
            : null;

        $result = $refresher->refresh($fetchedAt, $quarter);

        $this->info(sprintf(
            'Industry WACC refresh completed with %d rate(s) and %d superseded row(s).',
            $result['rates_refreshed'],
            $result['rows_superseded'],
        ));

        return self::SUCCESS;
    }
}
