<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Panels\Coach\SignalDetector;
use Illuminate\Console\Command;

final class RunCoachSignalCalibrationLayer extends Command
{
    protected $signature = 'panels:coach-signal-calibration
        {--minimum-signals=3 : Minimum mapped signals before a governed candidate is queued}
        {--window-days=90 : Signal lookback window in days}';

    protected $description = 'Surface coach referral signal suggestions and queue governed calibration candidates.';

    public function handle(SignalDetector $detector): int
    {
        $run = $detector->runCalibrationLayer(
            minimumSignals: (int) $this->option('minimum-signals'),
            windowDays: (int) $this->option('window-days'),
        );

        $this->info("Coach signal calibration completed with {$run->candidates_created} candidate(s) created.");

        return self::SUCCESS;
    }
}
