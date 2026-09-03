<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\User;
use App\Services\Entrepreneurs\Assessment;
use App\Services\Entrepreneurs\Revision;
use App\Support\RequestContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class RunEntrepreneurPlanAssessment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly string $businessPlanId,
        public readonly int $advisorId,
    ) {}

    public function handle(RequestContext $context): void
    {
        $context->apply('system', []);
        $assessments = app(Assessment::class);
        $revisions = app(Revision::class);
        $plan = BusinessPlan::query()->find($this->businessPlanId);
        $advisor = User::query()->find($this->advisorId);

        if (! $plan instanceof BusinessPlan || ! $advisor instanceof User) {
            return;
        }

        $runningPlan = $assessments->markQueuedFirstPassRunning($plan, $advisor);
        if (! $runningPlan instanceof BusinessPlan) {
            return;
        }

        try {
            $assessment = $assessments->firstPass($runningPlan, $advisor);
            $revisions->recordAssessment($assessment, $advisor);
            $profile = $runningPlan->refresh()->entrepreneurProfile;
            $profile?->forceFill(['stage' => EntrepreneurStage::ASSESSMENT])->save();
        } catch (Throwable $exception) {
            $assessments->markQueuedFirstPassFailed($runningPlan, $advisor, $exception);
            report($exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(RequestContext::class)->apply('system', []);
        $plan = BusinessPlan::query()->find($this->businessPlanId);
        $advisor = User::query()->find($this->advisorId);

        if ($plan instanceof BusinessPlan && $advisor instanceof User) {
            app(Assessment::class)->markQueuedFirstPassFailed($plan, $advisor, $exception);
        }
    }
}
