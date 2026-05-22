<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analysis\FeedbackLearningLayer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunFeedbackLearningLayer extends Command
{
    protected $signature = 'analysis:feedback-learning
                            {--threshold=3 : Corrections required for a module candidate.}
                            {--window-days=30 : Rolling feedback window in days.}
                            {--window-end= : Optional ISO-8601 window end for deterministic runs.}';

    protected $description = 'Run the governed analysis feedback learning layer.';

    public function handle(FeedbackLearningLayer $layer): int
    {
        $windowEndInput = $this->option('window-end');
        $windowEnd = is_string($windowEndInput) && $windowEndInput !== ''
            ? Carbon::parse($windowEndInput)
            : null;

        $run = $layer->run(
            threshold: (int) $this->option('threshold'),
            windowDays: (int) $this->option('window-days'),
            windowEnd: $windowEnd,
        );

        $this->info("Feedback learning layer completed with {$run->candidates_created} candidate(s) created.");

        return self::SUCCESS;
    }
}
