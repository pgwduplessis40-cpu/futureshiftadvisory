<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateEligibleExecutiveSummary;
use App\Models\PlanAssessment;
use App\Services\Entrepreneurs\BusinessPlanExecutiveSummary;
use App\Services\Entrepreneurs\ExecutiveSummaryEligibility;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class RecoverEntrepreneurExecutiveSummary extends Command
{
    protected $signature = 'entrepreneurs:recover-executive-summary
        {assessment : The finalised assessment UUID.}
        {--queue : Queue the eligible summary retry; otherwise only check readiness.}';

    protected $description = 'Check or retry a missing automatic executive summary without changing the assessment or advisor decision.';

    public function handle(ExecutiveSummaryEligibility $eligibility, BusinessPlanExecutiveSummary $summaries, RequestContext $context): int
    {
        $context->apply('system', []);
        $assessment = PlanAssessment::query()->with('businessPlan.entrepreneurProfile')->find((string) $this->argument('assessment'));
        $plan = $assessment?->businessPlan;
        $profile = $plan?->entrepreneurProfile;
        if ($assessment === null || $plan === null || $profile === null) {
            $this->error('Assessment or entrepreneur plan not found.');

            return self::FAILURE;
        }

        $current = $eligibility->evaluate($plan, $profile);
        if (! $current['eligible'] || $current['assessment_id'] !== (string) $assessment->getKey()) {
            $this->error($current['reason'] ?? 'This is not the current eligible finalised assessment.');

            return self::FAILURE;
        }
        if ($summaries->status($plan, $profile)['usable']) {
            $this->info('The approved executive summary is already current. No retry is needed.');

            return self::SUCCESS;
        }
        if (! $this->option('queue')) {
            $this->info('Eligible for automatic summary recovery. Use --queue to request the retry.');

            return self::SUCCESS;
        }

        GenerateEligibleExecutiveSummary::dispatch((string) $assessment->getKey());
        $this->info('Summary retry requested. An existing queued or running job is protected against duplicate dispatch.');

        return self::SUCCESS;
    }
}
