<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ai\Integrity\BiasCalibration;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunBiasCalibration extends Command
{
    protected $signature = 'analysis:bias-calibration
                            {--min-findings=4 : Minimum findings required in a cohort and baseline.}
                            {--skew-threshold=0.5 : Minimum high-severity rate delta required for a signal.}
                            {--window-days=30 : Rolling analysis window in days.}
                            {--window-end= : Optional ISO-8601 window end for deterministic runs.}
                            {--skip-monitor : Process existing bias-monitor candidates without running the monitor first.}';

    protected $description = 'Create governed calibration candidates from systematic analysis bias signals.';

    public function handle(BiasCalibration $calibration): int
    {
        $windowEndInput = $this->option('window-end');
        $windowEnd = is_string($windowEndInput) && $windowEndInput !== ''
            ? Carbon::parse($windowEndInput)
            : null;

        $run = $calibration->run(
            minFindings: (int) $this->option('min-findings'),
            skewThreshold: (float) $this->option('skew-threshold'),
            windowDays: (int) $this->option('window-days'),
            windowEnd: $windowEnd,
            runMonitorFirst: ! (bool) $this->option('skip-monitor'),
        );

        $this->info("Bias calibration completed with {$run->candidates_created} candidate(s) created.");

        return self::SUCCESS;
    }
}
