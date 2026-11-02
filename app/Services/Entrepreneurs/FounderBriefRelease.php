<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type SummaryStatus array{usable:bool,context_hash:string|null}
 * @phpstan-type ReleaseReview array{can_approve:bool,approved:bool,note:string|null,approved_at:string|null,findings:list<string>}
 * @phpstan-type ReleaseStatus array{active:bool,label:string,reasons:list<string>,executive_summary:SummaryStatus,assessment_id:string|null,approval_token:string,review:ReleaseReview,summary_recovery:array{can_retry:bool,state:string,message:string}}
 */
final class FounderBriefRelease
{
    public function __construct(
        private readonly ExecutiveSummaryEligibility $eligibility,
        private readonly BusinessPlanExecutiveSummary $summaries,
        private readonly PlanIssueReadiness $readiness,
        private readonly ExecutiveSummaryRecovery $recovery,
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
        ], JSON_THROW_ON_ERROR));
        $review = data_get($assessment?->scoring_scope, 'brief_release_review');
        $approved = $eligible && $summary['usable'] && is_array($review)
            && is_string($review['token'] ?? null) && hash_equals($review['token'], $token);
        $active = $eligible && $summary['usable'] && ($approved || (bool) ($readiness['external_issue_ready'] ?? false));
        $state = $summary['usable'] ? 'complete' : ($assessment === null ? 'idle' : $this->recovery->state((string) $assessment->getKey()));
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
            ...(! $eligible ? [$eligibility['reason'] ?? 'Finalise the latest assessment before releasing this brief.'] : []),
            ...($eligible && ! $summary['usable'] ? [$summaryMessage] : []),
            ...$findings,
        ]));

        return [
            'active' => $active,
            'label' => $active ? 'Lender brief ready' : 'Lender brief blocked',
            'reasons' => $reasons,
            'executive_summary' => ['usable' => (bool) $summary['usable'], 'context_hash' => $eligibility['context_hash']],
            'assessment_id' => $assessment?->getKey(),
            'approval_token' => $token,
            'review' => [
                'can_approve' => $eligible && (bool) $summary['usable'] && ! $active,
                'approved' => $approved,
                'note' => $approved ? (string) ($review['note'] ?? '') : null,
                'approved_at' => $approved ? (string) ($review['approved_at'] ?? '') : null,
                'findings' => $findings,
            ],
            'summary_recovery' => ['can_retry' => $eligible && ! $summary['usable'] && ! $inFlight, 'state' => $state, 'message' => $summaryMessage],
        ];
    }

    public function approve(BusinessPlan $plan, EntrepreneurProfile $profile, User $advisor, string $token, string $note): void
    {
        $status = $this->evaluate($plan, $profile);
        if (! $status['review']['can_approve'] || ! hash_equals($status['approval_token'], $token)) {
            throw ValidationException::withMessages(['brief_release' => 'The brief or its findings changed, or the summary is not ready. Refresh and review the current version before approval.']);
        }
        $assessment = PlanAssessment::query()->lockForUpdate()->findOrFail($status['assessment_id']);
        $review = [
            'token' => $token, 'note' => $note, 'approved_at' => now()->toIso8601String(),
            'approved_by_user_id' => (int) $advisor->getKey(), 'findings' => $status['review']['findings'],
        ];
        $scope = (array) $assessment->scoring_scope;
        $scope['brief_release_review'] = $review;
        $assessment->forceFill(['scoring_scope' => $scope])->save();
        app(AuditWriter::class)->record('entrepreneur.brief_release_approved', subject: $assessment, actor: $advisor, after: $review);
    }
}
