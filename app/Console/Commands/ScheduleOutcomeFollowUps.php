<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Outcomes\OutcomeFollowUpService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class ScheduleOutcomeFollowUps extends Command
{
    protected $signature = 'outcomes:schedule-follow-ups
                            {--now= : Optional ISO-8601 timestamp for deterministic runs.}';

    protected $description = 'Schedule due 6 and 12 month post-engagement outcome follow-ups.';

    public function handle(OutcomeFollowUpService $outcomes): int
    {
        $nowInput = $this->option('now');
        $now = is_string($nowInput) && $nowInput !== ''
            ? Carbon::parse($nowInput)
            : null;

        $created = $outcomes->scheduleDue($now);

        $this->info("Outcome follow-up scheduling completed with {$created} follow-up(s) created.");

        return self::SUCCESS;
    }
}
