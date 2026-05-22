<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Pv\ValuationMultipleRefresher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RefreshValuationMultiples extends Command
{
    protected $signature = 'valuation-multiples:refresh
                            {--quarter= : Optional quarter label, for example 2026Q2.}
                            {--fetched-at= : Optional ISO-8601 fetch timestamp for deterministic runs.}';

    protected $description = 'Refresh NZ valuation multiple reference data and queue governed review candidates.';

    public function handle(ValuationMultipleRefresher $refresher): int
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
            'Valuation multiple refresh completed with %d multiple(s), %d superseded row(s), and %d candidate(s).',
            $result['multiples_refreshed'],
            $result['rows_superseded'],
            $result['candidates_created'],
        ));

        return self::SUCCESS;
    }
}
