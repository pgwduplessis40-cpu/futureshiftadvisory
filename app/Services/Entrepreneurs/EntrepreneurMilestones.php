<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurMilestoneAward;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\PlanAssessment;
use App\Models\PlanPhase;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

final class EntrepreneurMilestones
{
    public const IDEA_VALIDATED = 'idea_validated';

    public const PLAN_SUBMITTED = 'plan_submitted';

    public const FIRST_ASSESSMENT = 'first_assessment';

    public const GRADE_UP = 'grade_up';

    public const ADVISORY_READY = 'advisory_ready';

    private const SUBMITTED_OR_BEYOND = [
        BusinessPlan::STATUS_SUBMITTED,
        BusinessPlan::STATUS_ASSESSING,
        BusinessPlan::STATUS_REVISING,
        BusinessPlan::STATUS_FINALISED,
        BusinessPlan::STATUS_LAUNCHED,
        BusinessPlan::STATUS_FOUNDING,
    ];

    private const GRADE_RANK = [
        'needs_work' => 1,
        'developing' => 2,
        'strong' => 3,
        'exceptional' => 4,
    ];

    public function __construct(private readonly RequestContext $context) {}

    public static function labels(): array
    {
        return [
            self::IDEA_VALIDATED => 'Idea validated',
            'phase_1' => 'Foundation complete',
            'phase_2' => 'Market complete',
            'phase_3' => 'Strategy complete',
            'phase_4' => 'Legal and operations complete',
            'phase_5' => 'Financial complete',
            self::PLAN_SUBMITTED => 'Plan submitted',
            self::FIRST_ASSESSMENT => 'First assessment',
            self::GRADE_UP => 'Grade improved',
            self::ADVISORY_READY => 'Advisory ready',
        ];
    }

    public function reconcile(EntrepreneurProfile $profile): void
    {
        $profile->refresh();

        if (! $profile->gamification_on) {
            return;
        }

        $validations = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->whereNotNull('advisor_gate_passed_at')
            ->orderBy('advisor_gate_passed_at')
            ->get();

        foreach ($validations as $validation) {
            $this->awardIdeaValidated($validation);
        }

        $plans = BusinessPlan::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->with(['phases', 'sections', 'assessments.ratingFramework.criteria'])
            ->orderBy('created_at')
            ->get();

        foreach ($plans as $plan) {
            $this->awardCompletedPhases($plan, estimated: true);
            $this->awardPlanSubmitted($plan);

            foreach ($plan->assessments->sortBy('round') as $assessment) {
                $this->awardAssessmentFinalised($assessment);
            }
        }

        $signals = AdvisoryReadinessSignal::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->with('planAssessment.ratingFramework.criteria')
            ->orderBy('surfaced_at')
            ->get();

        foreach ($signals as $signal) {
            $this->awardAdvisoryReady($signal);
        }
    }

    public function awardIdeaValidated(IdeaValidation $validation): ?EntrepreneurMilestoneAward
    {
        if ($validation->advisor_gate_passed_at === null) {
            return null;
        }

        $profile = $validation->entrepreneurProfile;
        if (! $profile instanceof EntrepreneurProfile || ! $profile->gamification_on) {
            return null;
        }

        return $this->award(
            profile: $profile,
            key: self::IDEA_VALIDATED,
            evidenceType: 'idea_validation',
            evidenceId: (string) $validation->getKey(),
            earnedAt: $validation->advisor_gate_passed_at,
            snapshot: [
                'advisor_gate_passed_at' => $validation->advisor_gate_passed_at->toIso8601String(),
                'earned_at_estimated' => false,
            ],
        );
    }

    public function awardPlanSubmitted(BusinessPlan $plan): ?EntrepreneurMilestoneAward
    {
        if (! in_array($plan->status, self::SUBMITTED_OR_BEYOND, true)) {
            return null;
        }

        $profile = $plan->entrepreneurProfile;
        if (! $profile instanceof EntrepreneurProfile || ! $profile->gamification_on) {
            return null;
        }

        $estimated = $plan->submitted_at === null;
        $earnedAt = $plan->submitted_at ?? now();

        return $this->award(
            profile: $profile,
            key: self::PLAN_SUBMITTED,
            evidenceType: 'business_plan',
            evidenceId: (string) $plan->getKey(),
            earnedAt: $earnedAt,
            snapshot: [
                'business_plan_id' => $plan->getKey(),
                'status' => $plan->status,
                'submitted_at' => $plan->submitted_at?->toIso8601String(),
                'earned_at_estimated' => $estimated,
            ],
        );
    }

    public function awardCompletedPhases(BusinessPlan $plan, bool $estimated = false): void
    {
        $profile = $plan->entrepreneurProfile;
        if (! $profile instanceof EntrepreneurProfile || ! $profile->gamification_on) {
            return;
        }

        $plan->loadMissing('phases', 'sections');

        foreach (PlanRequirements::definitions() as $phaseKey => $definition) {
            if (! PlanRequirements::phaseComplete($plan, $phaseKey)) {
                continue;
            }

            $position = PlanRequirements::phasePosition($phaseKey);
            $phase = $plan->phases->firstWhere('key', $phaseKey);

            $this->award(
                profile: $profile,
                key: 'phase_'.$position,
                evidenceType: 'plan_phase',
                evidenceId: (string) ($phase instanceof PlanPhase ? $phase->getKey() : $phaseKey),
                earnedAt: now(),
                snapshot: [
                    'business_plan_id' => $plan->getKey(),
                    'phase_key' => $phaseKey,
                    'phase_title' => $definition['title'],
                    'earned_at_estimated' => $estimated,
                ],
            );
        }
    }

    public function awardAssessmentFinalised(PlanAssessment $assessment): void
    {
        if ($assessment->finalised_at === null) {
            return;
        }

        $assessment->loadMissing('businessPlan.entrepreneurProfile', 'ratingFramework.criteria');
        $plan = $assessment->businessPlan;
        $profile = $plan?->entrepreneurProfile;

        if (! $plan instanceof BusinessPlan || ! $profile instanceof EntrepreneurProfile || ! $profile->gamification_on) {
            return;
        }

        $snapshot = $this->assessmentSnapshot($assessment);

        $this->award(
            profile: $profile,
            key: self::FIRST_ASSESSMENT,
            evidenceType: 'plan_assessment',
            evidenceId: (string) $assessment->getKey(),
            earnedAt: $assessment->finalised_at,
            snapshot: $snapshot,
        );

        if ($this->isGradeImprovement($assessment)) {
            $this->award(
                profile: $profile,
                key: self::GRADE_UP,
                evidenceType: 'plan_assessment',
                evidenceId: (string) $assessment->getKey(),
                earnedAt: $assessment->finalised_at,
                snapshot: $snapshot,
                repeatable: true,
            );
        }
    }

    public function awardAdvisoryReady(AdvisoryReadinessSignal $signal): ?EntrepreneurMilestoneAward
    {
        $signal->loadMissing('entrepreneurProfile', 'planAssessment.ratingFramework.criteria');
        $profile = $signal->entrepreneurProfile;
        $assessment = $signal->planAssessment;

        if (
            ! $profile instanceof EntrepreneurProfile
            || ! $profile->gamification_on
            || ! $assessment instanceof PlanAssessment
            || $assessment->finalised_at === null
        ) {
            return null;
        }

        return $this->award(
            profile: $profile,
            key: self::ADVISORY_READY,
            evidenceType: 'advisory_readiness_signal',
            evidenceId: (string) $signal->getKey(),
            earnedAt: $assessment->finalised_at,
            snapshot: [
                ...$this->assessmentSnapshot($assessment),
                'advisory_readiness_signal_id' => $signal->getKey(),
                'readiness_score' => $signal->score,
                'surfaced_at' => $signal->surfaced_at?->toIso8601String(),
            ],
        );
    }

    public function markSeen(EntrepreneurProfile $profile): int
    {
        if (! $profile->gamification_on) {
            return 0;
        }

        return EntrepreneurMilestoneAward::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function award(
        EntrepreneurProfile $profile,
        string $key,
        string $evidenceType,
        string $evidenceId,
        CarbonInterface $earnedAt,
        array $snapshot,
        bool $repeatable = false,
    ): ?EntrepreneurMilestoneAward {
        return $this->context->withSystemContext(function () use ($profile, $key, $evidenceType, $evidenceId, $earnedAt, $snapshot, $repeatable): ?EntrepreneurMilestoneAward {
            $query = EntrepreneurMilestoneAward::query()
                ->where('entrepreneur_profile_id', $profile->getKey())
                ->where('milestone_key', $key);

            if ($repeatable) {
                $query->where('evidence_source_id', $evidenceId);
            }

            $existing = $query->first();
            if ($existing instanceof EntrepreneurMilestoneAward) {
                return $existing;
            }

            try {
                return EntrepreneurMilestoneAward::query()->create([
                    'entrepreneur_profile_id' => $profile->getKey(),
                    'milestone_key' => $key,
                    'evidence_source_type' => $evidenceType,
                    'evidence_source_id' => $evidenceId,
                    'evidence_snapshot' => $snapshot,
                    'earned_at' => $earnedAt,
                ]);
            } catch (QueryException) {
                return EntrepreneurMilestoneAward::query()
                    ->where('entrepreneur_profile_id', $profile->getKey())
                    ->where('milestone_key', $key)
                    ->when($repeatable, fn ($query) => $query->where('evidence_source_id', $evidenceId))
                    ->first();
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function assessmentSnapshot(PlanAssessment $assessment): array
    {
        return [
            'plan_assessment_id' => $assessment->getKey(),
            'round' => $assessment->round,
            'overall_grade' => $assessment->overall_grade,
            'weighted_score' => $this->weightedScore($assessment),
            'finalised_at' => $assessment->finalised_at?->toIso8601String(),
            'earned_at_estimated' => false,
        ];
    }

    private function weightedScore(PlanAssessment $assessment): float
    {
        $assessment->loadMissing('ratingFramework.criteria');
        $weights = $assessment->ratingFramework?->criteria?->pluck('weight', 'number') ?? collect();
        $aiScores = $this->scoresByCriterion($assessment->ai_scores);
        $advisorScores = $this->scoresByCriterion($assessment->advisor_scores);

        return round(($assessment->ratingFramework?->criteria ?? collect())->sum(function ($criterion) use ($weights, $aiScores, $advisorScores): float {
            $advisor = $advisorScores->get($criterion->number);
            $ai = $aiScores->get($criterion->number, []);
            $score = is_array($advisor) && is_numeric($advisor['score'] ?? null)
                ? (float) $advisor['score']
                : (float) ($ai['score'] ?? 0);

            return $score * (((float) $weights->get($criterion->number, 0)) / 100);
        }), 2);
    }

    private function scoresByCriterion(mixed $scores): Collection
    {
        if (! is_array($scores)) {
            return collect();
        }

        $isList = array_is_list($scores);

        return collect($scores)
            ->mapWithKeys(function (mixed $row, int|string $key) use ($isList): array {
                if (is_array($row)) {
                    $criterionNumber = (int) ($row['criterion_number'] ?? $this->criterionNumberFromKey($key, $isList));

                    if ($criterionNumber <= 0 || ! is_numeric($row['score'] ?? null)) {
                        return [];
                    }

                    return [$criterionNumber => $row];
                }

                if (! is_numeric($row)) {
                    return [];
                }

                $criterionNumber = $this->criterionNumberFromKey($key, $isList);

                return $criterionNumber > 0
                    ? [$criterionNumber => ['criterion_number' => $criterionNumber, 'score' => (float) $row]]
                    : [];
            });
    }

    private function criterionNumberFromKey(int|string $key, bool $isList): int
    {
        if (! is_numeric($key)) {
            return 0;
        }

        $number = (int) $key;

        return $isList ? $number + 1 : $number;
    }

    private function isGradeImprovement(PlanAssessment $assessment): bool
    {
        $currentRank = self::GRADE_RANK[$assessment->overall_grade] ?? 0;
        if ($currentRank === 0) {
            return false;
        }

        $previousMax = PlanAssessment::query()
            ->where('business_plan_id', $assessment->business_plan_id)
            ->where('round', '<', $assessment->round)
            ->whereNotNull('finalised_at')
            ->get()
            ->map(fn (PlanAssessment $previous): int => self::GRADE_RANK[$previous->overall_grade] ?? 0)
            ->max() ?? 0;

        return $previousMax > 0 && $currentRank > $previousMax;
    }
}
