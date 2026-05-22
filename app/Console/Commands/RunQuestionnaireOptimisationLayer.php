<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Questionnaires\QuestionnaireOptimisationLayer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunQuestionnaireOptimisationLayer extends Command
{
    protected $signature = 'questionnaires:optimisation-learning
                            {--minimum-responses=3 : Minimum submitted responses before a question can be flagged.}
                            {--blank-rate-threshold=0.5 : Minimum blank or omitted answer rate required for a candidate.}
                            {--window-days=90 : Rolling questionnaire response window in days.}
                            {--max-candidates=5 : Maximum candidates emitted in one run.}
                            {--window-end= : Optional ISO-8601 window end for deterministic runs.}';

    protected $description = 'Run the governed questionnaire optimisation learning layer.';

    public function handle(QuestionnaireOptimisationLayer $layer): int
    {
        $windowEndInput = $this->option('window-end');
        $windowEnd = is_string($windowEndInput) && $windowEndInput !== ''
            ? Carbon::parse($windowEndInput)
            : null;

        $run = $layer->run(
            minimumResponses: (int) $this->option('minimum-responses'),
            blankRateThreshold: (float) $this->option('blank-rate-threshold'),
            windowDays: (int) $this->option('window-days'),
            maxCandidates: (int) $this->option('max-candidates'),
            windowEnd: $windowEnd,
        );

        $this->info("Questionnaire optimisation layer completed with {$run->candidates_created} candidate(s) created.");

        return self::SUCCESS;
    }
}
