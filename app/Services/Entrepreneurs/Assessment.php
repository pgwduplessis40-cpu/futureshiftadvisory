<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\LearningUpdate;
use App\Models\PlanAssessment;
use App\Models\PlanSection;
use App\Models\RatingCriterion;
use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Assessment
{
    public const LEARNING_LAYER_ID = 19;

    public function __construct(
        private readonly RatingFrameworkManager $frameworks,
        private readonly PlanDocuments $documents,
        private readonly AiClient $ai,
        private readonly AuditWriter $audit,
    ) {}

    public function firstPass(BusinessPlan $plan, User $actor): PlanAssessment
    {
        $plan->loadMissing('sections', 'entrepreneurProfile');
        foreach ($plan->sections as $section) {
            if ($section instanceof PlanSection) {
                $this->documents->ensureScoringClear($section);
            }
        }

        $framework = $this->frameworks->published();
        $sectionsText = $plan->sections->pluck('body')->implode("\n");
        $aiScores = $framework->criteria
            ->map(fn (RatingCriterion $criterion): array => $this->scoreCriterion($criterion, $plan, $sectionsText))
            ->values()
            ->all();
        $documentSupport = $this->documentSupport($plan);
        $weighted = $this->weightedScore($framework, $aiScores);

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
        $assessment->forceFill([
            'advisor_scores' => $advisorScores,
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
        $assessment->forceFill([
            'finalised_at' => now(),
            'finalised_by_user_id' => $advisor->getKey(),
        ])->save();
        $assessment->businessPlan?->forceFill([
            'status' => BusinessPlan::STATUS_FINALISED,
            'completed_at' => now(),
        ])->save();

        return $assessment->refresh();
    }

    private function scoreCriterion(RatingCriterion $criterion, BusinessPlan $plan, string $sectionsText): array
    {
        $prompt = new PromptEnvelope(
            id: 'entrepreneur.plan_score_criterion',
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
        $base = $this->heuristicScore($criterion, $sectionsText);

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

    private function heuristicScore(RatingCriterion $criterion, string $sectionsText): int
    {
        $haystack = strtolower($sectionsText);
        $needles = collect(explode(' ', strtolower($criterion->name)))
            ->map(fn (string $word): string => trim($word))
            ->filter(fn (string $word): bool => strlen($word) > 3);
        $matches = $needles->filter(fn (string $word): bool => str_contains($haystack, $word))->count();
        $wordCount = str_word_count($sectionsText);

        return max(35, min(82, 48 + ($matches * 8) + min(18, (int) floor($wordCount / 25))));
    }

    /**
     * @param  array<int, array<string, mixed>>  $scores
     */
    private function weightedScore(RatingFramework $framework, array $scores): float
    {
        $weights = $framework->criteria->pluck('weight', 'number');

        return round(collect($scores)->sum(function (array $score) use ($weights): float {
            $weight = (float) $weights->get((int) $score['criterion_number'], 0);

            return ((float) $score['score']) * ($weight / 100);
        }), 2);
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
