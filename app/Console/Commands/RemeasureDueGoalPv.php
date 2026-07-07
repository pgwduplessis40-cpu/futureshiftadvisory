<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Goals\GoalTracker;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class RemeasureDueGoalPv extends Command
{
    protected $signature = 'goals:remeasure-due-pv
                            {--limit=50 : Maximum due goals to re-measure.}';

    protected $description = 'Re-measure PV for active goals whose target date has arrived.';

    public function handle(GoalTracker $goals, RequestContext $context): int
    {
        $context->apply('system', []);

        $result = $goals->remeasureDueGoals((int) $this->option('limit'));

        $this->info(sprintf(
            'Scanned %d due goal(s): %d re-measured, %d failed.',
            $result['scanned'],
            $result['remeasured'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
