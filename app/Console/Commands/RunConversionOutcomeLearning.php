<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Learning\Layers\ConversionOutcomeLearning;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunConversionOutcomeLearning extends Command
{
    protected $signature = 'learning:conversion-outcomes
                            {--window-days=1095 : Rolling conversion-outcome window in days.}
                            {--window-end= : Optional ISO-8601 window end for deterministic runs.}';

    protected $description = 'Create governed learning candidates from conversion outcomes.';

    public function handle(ConversionOutcomeLearning $learning): int
    {
        $windowEndInput = $this->option('window-end');
        $windowEnd = is_string($windowEndInput) && $windowEndInput !== ''
            ? Carbon::parse($windowEndInput)
            : null;

        $run = $learning->run(
            windowDays: (int) $this->option('window-days'),
            windowEnd: $windowEnd,
        );

        $this->info("Conversion outcome learning completed with {$run->candidates_created} candidate(s) created.");

        return self::SUCCESS;
    }
}
