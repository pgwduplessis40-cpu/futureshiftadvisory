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
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        private readonly PlanAiContext $contexts,
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
        $aiScores = $framework->criteria
            ->map(fn (RatingCriterion $criterion): array => $this->scoreCriterion(
                criterion: $criterion,
                plan: $plan,
                planContext: $this->contexts->criterionAssessment(
                    plan: $plan,
                    criterion: $criterion,
                    budgetSummary: $this->budgetAssessmentText($plan->budgetRunway),
                ),
            ))
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
        $notes = $assessment->mentor_notes;
        if (! is_array($notes)) {
            $notes = [];
        }

        $assessment->forceFill([
            'mentor_notes' => [
                ...$notes,
                'section_notes' => $sectionNotes,
                'overall_visible' => $overallVisible,
                'private_advisory' => $privateAdvisory,
                'updated_by_user_id' => $advisor->getKey(),
            ],
        ])->save();

        return $assessment->refresh();
    }

    public function saveAdvisorFeedback(
        PlanAssessment $assessment,
        string $feedback,
        string $proposedReply,
        bool $sentToFounder,
        User $advisor,
        array $feedbackSnapshot = [],
    ): PlanAssessment {
        $notes = $assessment->mentor_notes;
        if (! is_array($notes)) {
            $notes = [];
        }

        $notes['advisor_feedback'] = trim($feedback);
        $notes['proposed_reply'] = trim($proposedReply);
        $notes['updated_by_user_id'] = $advisor->getKey();
        $notes['updated_at'] = now()->toIso8601String();
        $notes['feedback_snapshot'] = $this->feedbackSnapshotWithEdits(
            snapshot: $feedbackSnapshot,
            feedback: $feedback,
            proposedReply: $proposedReply,
            sentToFounder: $sentToFounder,
            advisor: $advisor,
        );

        if ($sentToFounder) {
            $notes['overall_visible'] = trim($feedback);
            $notes['feedback_sent_at'] = now()->toIso8601String();
            $notes['feedback_sent_by_user_id'] = $advisor->getKey();
        }

        $assessment->forceFill([
            'mentor_notes' => $notes,
        ])->save();

        $this->audit->record(
            $sentToFounder
                ? 'entrepreneur.plan_assessment_feedback_sent'
                : 'entrepreneur.plan_assessment_feedback_saved',
            subject: $assessment,
            actor: $advisor,
            after: [
                'business_plan_id' => $assessment->business_plan_id,
                'round' => $assessment->round,
                'feedback_changed_from_suggestion' => data_get($notes, 'feedback_snapshot.advisor_edits.feedback_changed_from_suggestion'),
                'proposed_reply_changed_from_suggestion' => data_get($notes, 'feedback_snapshot.advisor_edits.proposed_reply_changed_from_suggestion'),
            ],
        );

        return $assessment->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function entrepreneurVisibleNotes(PlanAssessment $assessment): array
    {
        $notes = $assessment->mentor_notes ?? [];
        if (! is_array($notes)) {
            return [];
        }

        unset(
            $notes['private_advisory'],
            $notes['advisor_feedback'],
            $notes['proposed_reply'],
            $notes['feedback_sent_at'],
            $notes['feedback_sent_by_user_id'],
            $notes['feedback_snapshot'],
            $notes['updated_by_user_id'],
            $notes['updated_at'],
        );

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

    /**
     * @param  array{relevant_sections:array<int, array{title:string,body_excerpt:string,requirement_key:string|null}>,supporting_section_summaries:array<int, array{title:string,body_excerpt:string,requirement_key:string|null}>,budget_summary:string}  $planContext
     */
    private function scoreCriterion(RatingCriterion $criterion, BusinessPlan $plan, array $planContext): array
    {
        $prompt = new PromptEnvelope(
            id: EntrepreneurPromptRegistry::PLAN_SCORE_CRITERION,
            version: '2026-07-30',
            task: 'Score one entrepreneur business-plan criterion honestly against the current rating framework.',
            body: 'Return JSON only. Set metadata.score to an honest integer from 0 to 100 and set text to the rationale. Score only the supplied, criterion-relevant evidence. Do not flatter weak evidence.',
            input: [
                'business_plan_id' => $plan->getKey(),
                'criterion' => [
                    'number' => $criterion->number,
                    'name' => $criterion->name,
                    'descriptors' => $criterion->descriptors,
                ],
                'plan_context' => $planContext,
            ],
            dataQualitySummary: [
                'level' => 'draft_plan',
            ],
            sourceReferences: ['business_plan:'.$plan->getKey(), 'rating_criterion:'.$criterion->getKey()],
        );
        $response = $this->ai->scoreCriterion($prompt);
        $fallbackScore = $this->heuristicScore($criterion, $plan, $this->contexts->assessmentText($planContext));
        $aiScore = $this->scoreFromResponse($response);
        $score = $aiScore ?? $fallbackScore;
        $scoreSource = $aiScore === null ? 'deterministic_fallback' : 'ai_assessment';

        return [
            'criterion_id' => $criterion->getKey(),
            'criterion_number' => $criterion->number,
            'criterion_name' => $criterion->name,
            'score' => $score,
            'score_source' => $scoreSource,
            'rationale' => $scoreSource === 'ai_assessment'
                ? $this->rationaleFromResponse($response, $score)
                : $this->fallbackRationale($fallbackScore),
            'attributions' => [
                ...$response->attributions,
                [
                    'claim' => 'Criterion score derived from current business plan draft.',
                    'source_reference' => 'business_plan:'.$plan->getKey(),
                ],
            ],
            'model' => $response->model,
            'metadata' => [
                'ai_score' => $aiScore,
                'fallback_score' => $fallbackScore,
                'score_source' => $scoreSource,
                'uncertainty' => $response->uncertainty->value,
                'context_characters' => Str::length($this->contexts->assessmentText($planContext)),
            ],
        ];
    }

    private function scoreFromResponse(AiResponse $response): ?int
    {
        $candidate = data_get($response->metadata, 'score')
            ?? data_get($response->metadata, 'criterion_score')
            ?? data_get($response->metadata, 'score_0_100');

        if (! is_numeric($candidate)) {
            return null;
        }

        return max(0, min(100, (int) round((float) $candidate)));
    }

    private function rationaleFromResponse(AiResponse $response, int $score): string
    {
        $text = trim($response->text);

        if ($text !== '') {
            return $text;
        }

        return $score < 60
            ? 'AI first-pass score is conservative because draft evidence is incomplete.'
            : 'AI first-pass score reflects current draft evidence and framework descriptors.';
    }

    private function fallbackRationale(int $score): string
    {
        return $score < 60
            ? 'Deterministic first-pass fallback used because the AI response did not include a valid score; draft evidence is incomplete.'
            : 'Deterministic first-pass fallback used because the AI response did not include a valid score; review before relying on this criterion.';
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

        if (($computed['break_even_reached'] ?? false) === true || data_get($computed, 'break_even_year') !== null) {
            $score += 5;
        }

        if (data_get($computed, 'cash_flow_positive_year') !== null) {
            $score += 5;
        }

        if (data_get($computed, 'first_profitable_year') !== null) {
            $score += 3;
        }

        if ($budget->expected_runway_months !== null && is_int($computed['runway_months'] ?? null)) {
            $score += 5;
        }

        if ((array) data_get($computed, 'missing_assumptions', []) !== []) {
            $score -= 8;
        }

        if (! (bool) data_get($computed, 'assumptions.company_tax_configured', false)) {
            $score -= 3;
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
            'Budget status: %s. Forecast horizon: %s years. Expected runway: %s months. Calculated runway: %s months. Break-even year: %s. First profitable year: %s. Cash-flow-positive year: %s. Available after launch: %s. Active budget warnings: %s.',
            $budget->status,
            $budget->forecast_years ?? data_get($computed, 'forecast_years', 3),
            $budget->expected_runway_months ?? 'not entered',
            data_get($computed, 'runway_months', 'not calculated'),
            data_get($computed, 'break_even_year', 'not reached'),
            data_get($computed, 'first_profitable_year', 'not reached'),
            data_get($computed, 'cash_flow_positive_year', 'not reached'),
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

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function feedbackSnapshotWithEdits(
        array $snapshot,
        string $feedback,
        string $proposedReply,
        bool $sentToFounder,
        User $advisor,
    ): array {
        $feedback = trim($feedback);
        $proposedReply = trim($proposedReply);
        $feedbackHash = $this->feedbackTextHash($feedback);
        $replyHash = $this->feedbackTextHash($proposedReply);
        $suggestedFeedbackHash = data_get($snapshot, 'suggested_feedback.sha256');
        $suggestedReplyHash = data_get($snapshot, 'suggested_reply.sha256');

        return [
            ...$snapshot,
            'saved_at' => now()->toIso8601String(),
            'saved_by_user_id' => $advisor->getKey(),
            'sent_to_founder' => $sentToFounder,
            'advisor_edits' => [
                'feedback_sha256' => $feedbackHash,
                'proposed_reply_sha256' => $replyHash,
                'feedback_changed_from_suggestion' => is_string($suggestedFeedbackHash)
                    ? $feedbackHash !== $suggestedFeedbackHash
                    : null,
                'proposed_reply_changed_from_suggestion' => is_string($suggestedReplyHash)
                    ? $replyHash !== $suggestedReplyHash
                    : null,
                'feedback_length_delta' => Str::length($feedback) - (int) data_get($snapshot, 'suggested_feedback.length', 0),
                'proposed_reply_length_delta' => Str::length($proposedReply) - (int) data_get($snapshot, 'suggested_reply.length', 0),
            ],
        ];
    }

    private function feedbackTextHash(string $text): string
    {
        return hash('sha256', Str::squish($text));
    }
}
