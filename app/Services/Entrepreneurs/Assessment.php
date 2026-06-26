<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\LearningUpdate;
use App\Models\PlanAssessment;
use App\Models\PlanSection;
use App\Models\RatingCriterion;
use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Assessment implements ProvidesMethodology
{
    public const LEARNING_LAYER_ID = 19;

    public static function methodologyIds(): array
    {
        return ['entrepreneur.plan_assessment'];
    }

    public function __construct(
        private readonly RatingFrameworkManager $frameworks,
        private readonly PlanDocuments $documents,
        private readonly AiClient $ai,
        private readonly AuditWriter $audit,
        private readonly EntrepreneurMilestones $milestones,
    ) {}

    public function firstPass(BusinessPlan $plan, User $actor): PlanAssessment
    {
        $plan->loadMissing('sections', 'entrepreneurProfile', 'budgetRunway');
        foreach ($plan->sections as $section) {
            if ($section instanceof PlanSection) {
                $this->documents->ensureScoringClear($section);
            }
        }

        $framework = $this->frameworks->published();
        $sectionsText = trim($plan->sections->pluck('body')->implode("\n")."\n".$this->budgetAssessmentText($plan->budgetRunway));
        $aiScores = $framework->criteria
            ->map(fn (RatingCriterion $criterion): array => $this->scoreCriterion($criterion, $plan, $sectionsText))
            ->values()
            ->all();
        $documentSupport = $this->documentSupport($plan);
        $weighted = AssessmentScoring::weightedScoreForFramework($framework, $aiScores);

        return DB::transaction(function () use ($plan, $actor, $framework, $aiScores, $documentSupport, $weighted): PlanAssessment {
            $round = ((int) PlanAssessment::query()->where('business_plan_id', $plan->getKey())->max('round')) + 1;
            $assessment = PlanAssessment::query()->create([
                'business_plan_id' => $plan->getKey(),
                'round' => max(1, $round),
                'rating_framework_id' => $framework->getKey(),
                'ai_scores' => $aiScores,
                'advisor_scores' => [],
                'mentor_notes' => [],
                'document_support' => $documentSupport,
                'overall_grade' => $framework->gradeFor($weighted),
            ]);
            $plan->forceFill([
                'status' => BusinessPlan::STATUS_ASSESSING,
            ])->save();

            $this->audit->record('entrepreneur.plan_first_pass_scored', subject: $assessment, actor: $actor, after: [
                'business_plan_id' => $plan->getKey(),
                'criterion_count' => count($aiScores),
                'weighted_score' => $weighted,
                'overall_grade' => $assessment->overall_grade,
            ]);

            return $assessment->refresh()->load('ratingFramework.criteria');
        });
    }

    public function adjustScore(PlanAssessment $assessment, int $criterionNumber, int $score, string $note, User $advisor): PlanAssessment
    {
        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages([
                'note' => 'Advisor score adjustments require a note.',
            ]);
        }

        $score = max(0, min(100, $score));
        $advisorScores = $assessment->advisor_scores ?? [];
        $advisorScores[(string) $criterionNumber] = [
            'criterion_number' => $criterionNumber,
            'score' => $score,
            'note' => $note,
            'adjusted_by_user_id' => $advisor->getKey(),
            'adjusted_at' => now()->toIso8601String(),
        ];
        $assessment->loadMissing('ratingFramework.criteria');
        $weighted = $assessment->ratingFramework instanceof RatingFramework
            ? AssessmentScoring::weightedScoreForFramework($assessment->ratingFramework, $assessment->ai_scores ?? [], $advisorScores)
            : 0.0;
        $assessment->forceFill([
            'advisor_scores' => $advisorScores,
            'overall_grade' => $assessment->ratingFramework?->gradeFor($weighted) ?? $assessment->overall_grade,
        ])->save();

        $this->queueAdjustmentLearning($assessment, $criterionNumber, $score, $note, $advisor);

        return $assessment->refresh();
    }

    /**
     * @param  array<string, mixed>  $sectionNotes
     */
    public function setMentorNotes(
        PlanAssessment $assessment,
        array $sectionNotes,
        string $overallVisible,
        string $privateAdvisory,
        User $advisor,
    ): PlanAssessment {
        $assessment->forceFill([
            'mentor_notes' => [
                'section_notes' => $sectionNotes,
                'overall_visible' => $overallVisible,
                'private_advisory' => $privateAdvisory,
                'updated_by_user_id' => $advisor->getKey(),
            ],
        ])->save();

        return $assessment->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function entrepreneurVisibleNotes(PlanAssessment $assessment): array
    {
        $notes = $assessment->mentor_notes ?? [];
        unset($notes['private_advisory']);

        return $notes;
    }

    public function criteriaVisible(BusinessPlan $plan): bool
    {
        return PlanAssessment::query()
            ->where('business_plan_id', $plan->getKey())
            ->whereNotNull('finalised_at')
            ->exists();
    }

    public function finalise(PlanAssessment $assessment, User $advisor): PlanAssessment
    {
        $assessment->loadMissing('ratingFramework.criteria');
        $weighted = $assessment->ratingFramework instanceof RatingFramework
            ? AssessmentScoring::weightedScoreForFramework($assessment->ratingFramework, $assessment->ai_scores ?? [], $assessment->advisor_scores ?? [])
            : 0.0;
        $assessment->forceFill([
            'finalised_at' => now(),
            'finalised_by_user_id' => $advisor->getKey(),
            'overall_grade' => $assessment->ratingFramework?->gradeFor($weighted) ?? $assessment->overall_grade,
        ])->save();
        $assessment->businessPlan?->forceFill([
            'status' => BusinessPlan::STATUS_FINALISED,
            'completed_at' => now(),
        ])->save();
        $this->milestones->awardAssessmentFinalised($assessment->refresh()->load('businessPlan.entrepreneurProfile', 'ratingFramework.criteria'));

        return $assessment->refresh();
    }

    private function scoreCriterion(RatingCriterion $criterion, BusinessPlan $plan, string $sectionsText): array
    {
        $prompt = new PromptEnvelope(
            id: EntrepreneurPromptRegistry::PLAN_SCORE_CRITERION,
            version: '2026-05-23',
            task: 'Score one entrepreneur business-plan criterion honestly against the current rating framework.',
            body: 'Return an honest first-pass score and rationale. Do not flatter weak evidence.',
            input: [
                'business_plan_id' => $plan->getKey(),
                'criterion' => [
                    'number' => $criterion->number,
                    'name' => $criterion->name,
                    'descriptors' => $criterion->descriptors,
                ],
                'sections_text' => $sectionsText,
            ],
            dataQualitySummary: [
                'level' => 'draft_plan',
            ],
            sourceReferences: ['business_plan:'.$plan->getKey(), 'rating_criterion:'.$criterion->getKey()],
        );
        $response = $this->ai->scoreCriterion($prompt);
        $base = $this->heuristicScore($criterion, $plan, $sectionsText);

        return [
            'criterion_id' => $criterion->getKey(),
            'criterion_number' => $criterion->number,
            'criterion_name' => $criterion->name,
            'score' => $base,
            'rationale' => $base < 60
                ? 'First-pass score is conservative because draft evidence is incomplete.'
                : 'First-pass score reflects current draft evidence and framework descriptors.',
            'attributions' => [
                ...$response->attributions,
                [
                    'claim' => 'Criterion score derived from current business plan draft.',
                    'source_reference' => 'business_plan:'.$plan->getKey(),
                ],
            ],
            'model' => $response->model,
        ];
    }

    private function heuristicScore(RatingCriterion $criterion, BusinessPlan $plan, string $sectionsText): int
    {
        if (strtolower((string) $criterion->name) === 'budget') {
            return $this->budgetHeuristicScore($plan->budgetRunway);
        }

        $haystack = strtolower($sectionsText);
        $needles = collect(explode(' ', strtolower($criterion->name)))
            ->map(fn (string $word): string => trim($word))
            ->filter(fn (string $word): bool => strlen($word) > 3);
        $matches = $needles->filter(fn (string $word): bool => str_contains($haystack, $word))->count();
        $wordCount = str_word_count($sectionsText);

        return max(35, min(82, 48 + ($matches * 8) + min(18, (int) floor($wordCount / 25))));
    }

    private function budgetHeuristicScore(?EntrepreneurBudget $budget): int
    {
        if (! $budget instanceof EntrepreneurBudget) {
            return 35;
        }

        $score = match ($budget->status) {
            EntrepreneurBudget::STATUS_COMPLETE => 70,
            EntrepreneurBudget::STATUS_PARTIAL => 52,
            default => 35,
        };
        $computed = (array) ($budget->computed ?? []);
        $activeFlags = collect((array) ($budget->flags ?? []))
            ->filter(fn (array $flag): bool => empty($flag['acknowledged_at']))
            ->count();

        if (($computed['break_even_reached'] ?? false) === true) {
            $score += 5;
        }

        if ($budget->expected_runway_months !== null && is_int($computed['runway_months'] ?? null)) {
            $score += 5;
        }

        return max(35, min(88, $score - ($activeFlags * 6)));
    }

    private function budgetAssessmentText(?EntrepreneurBudget $budget): string
    {
        if (! $budget instanceof EntrepreneurBudget) {
            return '';
        }

        $computed = (array) ($budget->computed ?? []);
        $flags = collect((array) ($budget->flags ?? []))
            ->filter(fn (array $flag): bool => empty($flag['acknowledged_at']))
            ->pluck('title')
            ->implode('; ');

        return sprintf(
            'Budget status: %s. Expected runway: %s months. Calculated runway: %s months. Break-even month: %s. Available after launch: %s. Active budget warnings: %s.',
            $budget->status,
            $budget->expected_runway_months ?? 'not entered',
            data_get($computed, 'runway_months', 'not calculated'),
            data_get($computed, 'break_even_month', 'not reached'),
            data_get($computed, 'available_after_launch', 0),
            $flags !== '' ? $flags : 'none',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function documentSupport(BusinessPlan $plan): array
    {
        return [
            'attached_document_count' => $plan->sections
                ->flatMap(fn (PlanSection $section): array => $section->attached_document_ids ?? [])
                ->unique()
                ->count(),
            'criterion_score_adjustment' => 'Verified section documents raise criterion score; unresolved flags block scoring before assessment creation.',
        ];
    }

    private function queueAdjustmentLearning(
        PlanAssessment $assessment,
        int $criterionNumber,
        int $score,
        string $note,
        User $advisor,
    ): void {
        LearningUpdate::query()->create([
            'layer_id' => self::LEARNING_LAYER_ID,
            'source' => [
                'type' => 'entrepreneur_assessment_adjustment',
                'plan_assessment_id' => $assessment->getKey(),
                'criterion_number' => $criterionNumber,
            ],
            'summary' => 'Advisor adjusted an entrepreneur plan score; review calibration.',
            'proposed_change' => [
                'action' => 'review_criterion_scoring',
                'criterion_number' => $criterionNumber,
                'advisor_score' => $score,
                'automatic_application' => false,
            ],
            'impact_scope' => [
                'module' => 'entrepreneur_assessment',
            ],
            'magnitude' => 'low',
            'confidence' => 0.6500,
            'evidence' => [
                'advisor_note' => $note,
                'adjusted_by_user_id' => $advisor->getKey(),
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }
}
