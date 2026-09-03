<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use Illuminate\Validation\ValidationException;

/**
 * The single business rule for whether a lender-facing executive summary may
 * be generated. A finalised assessment is necessary but not sufficient: the
 * assessed revision must still be current and meet the published pass bar.
 */
final class ExecutiveSummaryEligibility
{
    /** @var list<string> */
    public const PASSING_GRADES = ['strong', 'exceptional'];

    private const SNAPSHOT_CONTEXT_HASH = 'executive_summary_context_hash';

    public function __construct(
        private readonly ExecutiveSummaryContext $contexts,
    ) {}

    /**
     * @return array{eligible:bool,reason:string|null,assessment_id:string|null,context_hash:string,assessed_context_hash:string|null}
     */
    public function evaluate(BusinessPlan $plan, EntrepreneurProfile $profile): array
    {
        $plan->loadMissing('assessments.ratingFramework.criteria', 'sections.phase', 'budgetRunway');
        $contextHash = $this->contexts->hash($plan, $profile);
        $assessment = $plan->assessments
            ->filter(fn (PlanAssessment $candidate): bool => $candidate->finalised_at !== null)
            ->sortByDesc('round')
            ->first();

        if (! $assessment instanceof PlanAssessment) {
            return $this->blocked('A finalised Business Plan & Budget assessment is required before the executive summary can be generated.', $contextHash);
        }

        if (AssessmentScoring::hasFallbackScores($assessment) || AssessmentScoring::hasIncompleteScores($assessment)) {
            return $this->blocked('The finalised assessment does not contain valid scores for every criterion. Run and finalise a fresh assessment before generating the executive summary.', $contextHash, $assessment);
        }

        if (! in_array((string) $assessment->overall_grade, self::PASSING_GRADES, true)) {
            return $this->blocked('The finalised assessment has not reached the executive-summary pass threshold.', $contextHash, $assessment);
        }

        $assessedContextHash = data_get($assessment->plan_snapshot, self::SNAPSHOT_CONTEXT_HASH);
        if (! is_string($assessedContextHash) || $assessedContextHash === '') {
            return $this->blocked('This assessment predates executive-summary eligibility. Reassess the current plan and budget before generating the executive summary.', $contextHash, $assessment);
        }

        if (! hash_equals($assessedContextHash, $contextHash)) {
            return $this->blocked('The plan or budget changed after assessment. Reassess the current revision before generating the executive summary.', $contextHash, $assessment, $assessedContextHash);
        }

        return [
            'eligible' => true,
            'reason' => null,
            'assessment_id' => (string) $assessment->getKey(),
            'context_hash' => $contextHash,
            'assessed_context_hash' => $assessedContextHash,
        ];
    }

    public function require(BusinessPlan $plan, EntrepreneurProfile $profile): PlanAssessment
    {
        $eligibility = $this->evaluate($plan, $profile);
        if (! $eligibility['eligible'] || $eligibility['assessment_id'] === null) {
            throw ValidationException::withMessages([
                'executive_summary' => $eligibility['reason'] ?? 'The executive summary is not eligible for generation.',
            ]);
        }

        return PlanAssessment::query()->findOrFail($eligibility['assessment_id']);
    }

    public function recordAssessmentRevision(PlanAssessment $assessment, BusinessPlan $plan, EntrepreneurProfile $profile): PlanAssessment
    {
        $snapshot = is_array($assessment->plan_snapshot) ? $assessment->plan_snapshot : [];
        $snapshot[self::SNAPSHOT_CONTEXT_HASH] = $this->contexts->hash($plan, $profile);

        $assessment->forceFill(['plan_snapshot' => $snapshot])->save();

        return $assessment->refresh();
    }

    /**
     * @return array{eligible:false,reason:string,assessment_id:string|null,context_hash:string,assessed_context_hash:string|null}
     */
    private function blocked(string $reason, string $contextHash, ?PlanAssessment $assessment = null, ?string $assessedContextHash = null): array
    {
        return [
            'eligible' => false,
            'reason' => $reason,
            'assessment_id' => $assessment?->getKey() === null ? null : (string) $assessment->getKey(),
            'context_hash' => $contextHash,
            'assessed_context_hash' => $assessedContextHash,
        ];
    }
}
