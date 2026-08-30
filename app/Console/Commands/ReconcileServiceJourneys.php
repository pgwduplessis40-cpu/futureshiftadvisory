<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Journeys\ServiceJourney;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class ReconcileServiceJourneys extends Command
{
    protected $signature = 'service-journeys:reconcile
        {client? : Optional client UUID to reconcile.}';

    protected $description = 'Reconcile enabled service-journey recognition from verified service facts.';

    public function handle(ServiceJourney $journeys, RequestContext $context): int
    {
        $context->apply('system', []);

        $count = $journeys->reconcileEnabled($this->argument('client'));

        $this->info("Reconciled {$count} enabled service journey(s).");

        return self::SUCCESS;
    }
}
