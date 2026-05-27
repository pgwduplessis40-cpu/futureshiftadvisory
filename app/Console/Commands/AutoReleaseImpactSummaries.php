<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reports\ReportComposer;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class AutoReleaseImpactSummaries extends Command
{
    protected $signature = 'npo:impact-summary-auto-release';

    protected $description = 'Auto-release due client-authored NPO Impact Summaries after their advisor review window.';

    public function handle(ReportComposer $reports, RequestContext $context): int
    {
        $context->apply('system', []);

        $released = $reports->autoReleaseDueImpactSummaries();

        $this->info($released.' impact summar'.($released === 1 ? 'y' : 'ies').' auto-released.');

        return self::SUCCESS;
    }
}
