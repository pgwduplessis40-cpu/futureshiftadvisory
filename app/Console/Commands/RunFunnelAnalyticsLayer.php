<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analytics\FunnelTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunFunnelAnalyticsLayer extends Command
{
    protected $signature = 'analytics:funnel-learning
                            {--minimum-entered=3 : Minimum entered events before a candidate is emitted.}
                            {--window-days=30 : Rolling funnel window in days.}
                            {--window-end= : Optional ISO-8601 window end for deterministic runs.}';

    protected $description = 'Run the governed funnel analytics UX-improvement layer.';

    public function handle(FunnelTracker $funnels): int
    {
        $windowEndInput = $this->option('window-end');
        $windowEnd = is_string($windowEndInput) && $windowEndInput !== ''
            ? Carbon::parse($windowEndInput)
            : null;

        $run = $funnels->runMonthlySuggestions(
            minimumEntered: (int) $this->option('minimum-entered'),
            windowDays: (int) $this->option('window-days'),
            windowEnd: $windowEnd,
        );

        $this->info("Funnel analytics layer completed with {$run->candidates_created} candidate(s) created.");

        return self::SUCCESS;
    }
}
