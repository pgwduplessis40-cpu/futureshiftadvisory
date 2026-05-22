<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Compliance\LegislativeCurrencyMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class MonitorLegislativeCurrency extends Command
{
    protected $signature = 'legislative-currency:monitor
                            {--ran-at= : Optional ISO-8601 timestamp for deterministic runs.}';

    protected $description = 'Monitor NZ legislative currency feeds and queue governed compliance-review candidates.';

    public function handle(LegislativeCurrencyMonitor $monitor): int
    {
        $ranAtInput = $this->option('ran-at');
        $ranAt = is_string($ranAtInput) && $ranAtInput !== ''
            ? Carbon::parse($ranAtInput)
            : null;

        $result = $monitor->run($ranAt);

        $this->info(sprintf(
            'Legislative currency monitor completed with %d change(s) and %d candidate(s).',
            $result['changes_seen'],
            $result['candidates_created'],
        ));

        return self::SUCCESS;
    }
}
