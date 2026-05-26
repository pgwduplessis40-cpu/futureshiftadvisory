<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Templates\TemplateSuggestionLayer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunTemplateSuggestionLayer extends Command
{
    protected $signature = 'templates:suggest
                            {--window-days=90 : Rolling completed-report window in days.}
                            {--max-candidates=5 : Maximum candidates emitted in one run.}
                            {--window-end= : Optional ISO-8601 window end for deterministic runs.}';

    protected $description = 'Run the governed template suggestion learning layer.';

    public function handle(TemplateSuggestionLayer $layer): int
    {
        $windowEndInput = $this->option('window-end');
        $windowEnd = is_string($windowEndInput) && $windowEndInput !== ''
            ? Carbon::parse($windowEndInput)
            : null;

        $run = $layer->run(
            windowDays: (int) $this->option('window-days'),
            maxCandidates: (int) $this->option('max-candidates'),
            windowEnd: $windowEnd,
        );

        $this->info("Template suggestion layer completed with {$run->candidates_created} candidate(s) created.");

        return self::SUCCESS;
    }
}
