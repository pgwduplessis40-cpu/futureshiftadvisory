<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Learning\Layers\RatingPredictiveValidity;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunRatingPredictiveValidity extends Command
{
    protected $signature = 'learning:rating-validity-tests
                            {--window-days=1095 : Rolling conversion-outcome window in days.}
                            {--tested-at= : Optional ISO-8601 test timestamp.}
                            {--period= : Optional period label, e.g. 2026-H1.}';

    protected $description = 'Run semiannual rating predictive-validity tests.';

    public function handle(RatingPredictiveValidity $validity): int
    {
        $testedAtInput = $this->option('tested-at');
        $testedAt = is_string($testedAtInput) && $testedAtInput !== ''
            ? Carbon::parse($testedAtInput)
            : null;
        $periodInput = $this->option('period');

        $run = $validity->run(
            windowDays: (int) $this->option('window-days'),
            testedAt: $testedAt,
            period: is_string($periodInput) && $periodInput !== '' ? $periodInput : null,
        );

        $this->info("Rating predictive-validity testing completed with {$run->candidates_created} candidate(s) created.");

        return self::SUCCESS;
    }
}
