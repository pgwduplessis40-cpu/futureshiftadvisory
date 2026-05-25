<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Learning\Layers\PlanQualityBenchmarks;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunPlanQualityBenchmarks extends Command
{
    protected $signature = 'learning:plan-quality-benchmarks
                            {--window-days=365 : Rolling plan-quality benchmark window in days.}
                            {--window-end= : Optional ISO-8601 window end for deterministic runs.}';

    protected $description = 'Create governed entrepreneur plan-quality benchmark candidates.';

    public function handle(PlanQualityBenchmarks $benchmarks): int
    {
        $windowEndInput = $this->option('window-end');
        $windowEnd = is_string($windowEndInput) && $windowEndInput !== ''
            ? Carbon::parse($windowEndInput)
            : null;

        $run = $benchmarks->run(
            windowDays: (int) $this->option('window-days'),
            windowEnd: $windowEnd,
        );

        $this->info("Plan quality benchmarks completed with {$run->candidates_created} candidate(s) created.");

        return self::SUCCESS;
    }
}
