<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ai\Integrity\BiasMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunBiasMonitor extends Command
{
    protected $signature = 'analysis:bias-monitor
                            {--min-findings=4 : Minimum findings required in a cohort and baseline.}
                            {--skew-threshold=0.5 : Minimum high-severity rate delta required for a signal.}
                            {--window-days=30 : Rolling analysis window in days.}
                            {--window-end= : Optional ISO-8601 window end for deterministic runs.}';

    protected $description = 'Run the governed analysis bias monitoring layer.';

    public function handle(BiasMonitor $monitor): int
    {
        $windowEndInput = $this->option('window-end');
        $windowEnd = is_string($windowEndInput) && $windowEndInput !== ''
            ? Carbon::parse($windowEndInput)
            : null;

        $run = $monitor->run(
            minFindings: (int) $this->option('min-findings'),
            skewThreshold: (float) $this->option('skew-threshold'),
            windowDays: (int) $this->option('window-days'),
            windowEnd: $windowEnd,
        );

        $this->info("Bias monitor completed with {$run->candidates_created} candidate(s) created.");

        return self::SUCCESS;
    }
}
