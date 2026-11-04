<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type SummaryStatus array{usable:bool,context_hash:string|null}
 * @phpstan-type ReleaseReview array{can_approve:bool,approved:bool,note:string|null,approved_at:string|null,findings:list<string>}
 *
 * @phpstan-import-type Guide from PlanBudgetGuide
 *
 * @phpstan-type ReleaseStatus array{active:bool,can_generate:bool,generation_reason:string|null,label:string,reasons:list<string>,executive_summary:SummaryStatus,assessment_id:string|null,approval_token:string,review:ReleaseReview,plan_guidance:Guide,summary_recovery:array{can_retry:bool,state:string,message:string}}
 */
final class FounderBriefRelease
{
    public function __construct(
        private readonly ExecutiveSummaryEligibility $eligibility,
        private readonly BusinessPlanExecutiveSummary $summaries,
        private readonly PlanIssueReadiness $readiness,
        private readonly ExecutiveSummaryRecovery $recovery,
        private readonly PlanBudgetGuide $guide,
    ) {}

    /** @return ReleaseStatus */
    public function evaluate(BusinessPlan $plan, EntrepreneurProfile $profile): array
    {
        $summary = $this->summaries->status($plan, $profile);
        $eligibility = $this->eligibility->evaluate($plan, $profile);
        $assessment = $plan->assessments->sortByDesc('round')->first();
        $eligible = $eligibility['eligible'] && $assessment instanceof PlanAssessment
            && (string) $assessment->getKey() === $eligibility['assessment_id'];
        $readiness = $this->readiness->evaluate($plan);
        $complete = PlanRequirements::completion($plan)['complete']
            && $plan->budgetRunway?->status === EntrepreneurBudget::STATUS_COMPLETE;
        $canGenerate = $plan->sections->contains(fn ($section): bool => trim((string) $section->body) !== '') && $plan->budgetRunway !== null;
        $guidance = $this->guide->forPlan($plan);
        $aligned = $guidance['issues'] === [];
        $findings = collect((array) ($readiness['reasons'] ?? []))
            ->filter(fn (mixed $reason): bool => is_string($reason) && trim($reason) !== '')
            ->reject(fn (string $reason): bool => $reason === ($readiness['executive_summary_reason'] ?? null))
            ->map(fn (mixed $reason): string => (string) $reason)
            ->values()->all();
        $summaryBody = $plan->sections->firstWhere('id', $summary['section_id'])?->body;
        $token = hash('sha256', json_encode([
            'assessment_id' => $assessment?->getKey(),
            'context' => $eligibility['context_hash'],
            'summary' => $summaryBody,
            'findings' => $findings,
            'alignment' => $guidance['issues'],
        ], JSON_THROW_ON_ERROR));
        $approved = false;
        $active = (bool) data_get($readiness, 'release_gate.can_certify', false) && $canGenerate && $aligned;
        $state = $summary['usable'] ? 'complete' : ($assessment === null ? 'idle' : $this->recovery->state((string) $assessment->getKey()));
        // A completed job can describe an older version or an unusable fallback.
        // Only the current summary's eligibility can establish completion.
        if (! $summary['usable'] && $state === 'complete') {
            $state = 'idle';
        }
        $inFlight = in_array($state, ['queued', 'running', 'retrying'], true);
        $summaryMessage = match ($state) {
            'complete' => 'The approved summary is current.',
            'queued' => 'Summary generation is queued. This page will update automatically.',
            'running' => 'The summary is being generated. This page will update automatically.',
            'retrying' => 'Summary generation is retrying after a temporary failure.',
            'failed' => 'Summary generation failed. Retry it here; no new assessment is needed.',
            default => 'The assessment is finalised. Select Retry summary to complete the automatic summary.',
        };
        $reasons = $active ? [] : array_values(array_unique([
            ...(! $complete ? ['Complete the business plan and budget before external issue.'] : []),
            ...(! $eligible ? [$eligibility['reason'] ?? 'Finalise the latest assessment before releasing this brief.'] : []),
            ...($eligible && ! $summary['usable'] ? [$summaryMessage] : []),
            ...$findings,
            ...array_column($guidance['issues'], 'detail'),
        ]));

        return [
            'active' => $active,
            'can_generate' => $canGenerate,
            'generation_reason' => $canGenerate ? null : (! $complete
                ? 'Save plan content and a budget to generate a working draft.'
                : (! $eligible ? ($eligibility['reason'] ?? 'Finalise the latest assessment of the completed plan and budget before generating the founder brief.') : $summaryMessage)),
            'label' => $active ? 'Lender brief ready' : 'Draft - release checks outstanding',
            'reasons' => $reasons,
            'executive_summary' => ['usable' => (bool) $summary['usable'], 'context_hash' => $eligibility['context_hash']],
            'assessment_id' => $assessment?->getKey(),
            'approval_token' => $token,
            'plan_guidance' => $guidance,
            'review' => [
                'can_approve' => false,
                'approved' => $approved,
                'note' => null,
                'approved_at' => null,
                'findings' => $findings,
            ],
            'summary_recovery' => ['can_retry' => $eligible && ! $summary['usable'] && ! $inFlight, 'state' => $state, 'message' => $summaryMessage],
        ];
    }

    public function approve(BusinessPlan $plan, EntrepreneurProfile $profile, User $advisor, string $token, string $note): void
    {
        throw ValidationException::withMessages(['brief_release' => 'Use the current rule-level release review. A blanket approval cannot authorise external issue.']);
    }
}
