<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EconomicData\EconomicIndicatorRefresher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RefreshEconomicIndicators extends Command
{
    protected $signature = 'economic-indicators:refresh
                            {--fetched-at= : Optional ISO-8601 fetch timestamp for deterministic runs.}';

    protected $description = 'Refresh NZ economic indicators and exchange rates through the integration resilience layer.';

    public function handle(EconomicIndicatorRefresher $refresher): int
    {
        $fetchedAtInput = $this->option('fetched-at');
        $fetchedAt = is_string($fetchedAtInput) && $fetchedAtInput !== ''
            ? Carbon::parse($fetchedAtInput)
            : null;

        $result = $refresher->refresh($fetchedAt);

        $this->info(sprintf(
            'Economic indicator refresh completed with %d indicator(s), %d exchange rate(s), and %d candidate(s).',
            $result['indicators'],
            $result['exchange_rates'],
            $result['candidates_created'],
        ));

        return self::SUCCESS;
    }
}
