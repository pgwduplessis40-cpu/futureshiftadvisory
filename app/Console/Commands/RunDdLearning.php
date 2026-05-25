<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Learning\Layers\DdLearning;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunDdLearning extends Command
{
    protected $signature = 'learning:dd-learning
                            {--min-outcomes=1 : Minimum DD outcome records required for valuation learning.}
                            {--variance-threshold=0.15 : Average absolute variance rate required for valuation learning.}
                            {--pattern-threshold=3 : Repeated DD finding count required for checklist learning.}
                            {--window-days=180 : Rolling DD learning window in days.}
                            {--window-end= : Optional ISO-8601 window end for deterministic runs.}';

    protected $description = 'Create governed DD pattern and valuation-accuracy learning candidates.';

    public function handle(DdLearning $learning): int
    {
        $windowEndInput = $this->option('window-end');
        $windowEnd = is_string($windowEndInput) && $windowEndInput !== ''
            ? Carbon::parse($windowEndInput)
            : null;

        $run = $learning->run(
            minOutcomes: (int) $this->option('min-outcomes'),
            varianceThreshold: (float) $this->option('variance-threshold'),
            patternThreshold: (int) $this->option('pattern-threshold'),
            windowDays: (int) $this->option('window-days'),
            windowEnd: $windowEnd,
        );

        $this->info("DD learning completed with {$run->candidates_created} candidate(s) created.");

        return self::SUCCESS;
    }
}
