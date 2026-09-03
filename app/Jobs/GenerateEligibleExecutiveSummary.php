<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PlanAssessment;
use App\Services\Entrepreneurs\BusinessPlanExecutiveSummary;
use App\Services\Entrepreneurs\ExecutiveSummaryEligibility;
use App\Support\RequestContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class GenerateEligibleExecutiveSummary implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 180;

    public int $uniqueFor = 900;

    public function __construct(
        public readonly string $assessmentId,
    ) {}

    public function uniqueId(): string
    {
        return $this->assessmentId;
    }

    public function handle(
        RequestContext $context,
        ExecutiveSummaryEligibility $eligibility,
        BusinessPlanExecutiveSummary $summaries,
    ): void {
        $context->apply('system', []);
        $lock = Cache::lock('entrepreneur-executive-summary:'.$this->assessmentId, 300);

        try {
            $lock->block(1);
        } catch (LockTimeoutException) {
            return;
        }

        try {
            $assessment = PlanAssessment::query()
                ->with('businessPlan.entrepreneurProfile')
                ->find($this->assessmentId);
            $plan = $assessment?->businessPlan;
            $profile = $plan?->entrepreneurProfile;

            if ($assessment === null || $plan === null || $profile === null) {
                return;
            }

            $current = $eligibility->evaluate($plan, $profile);
            if (! $current['eligible'] || $current['assessment_id'] !== (string) $assessment->getKey()) {
                return;
            }

            $status = $summaries->status($plan, $profile);
            if ((bool) ($status['usable'] ?? false)) {
                return;
            }

            $generated = $summaries->generate($plan, $profile, null);
            if (! (bool) data_get($generated, 'executive_summary.usable', false)) {
                throw new RuntimeException('The qualifying executive-summary job did not receive a usable AI response.');
            }
        } finally {
            $lock->release();
        }
    }
}
