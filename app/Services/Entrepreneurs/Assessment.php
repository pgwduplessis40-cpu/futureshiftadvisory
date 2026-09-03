<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Jobs\GenerateEligibleExecutiveSummary;
use App\Models\BusinessPlan;
use App\Models\LearningUpdate;
use App\Models\PlanAssessment;
use App\Models\PlanRevision;
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
use RuntimeException;
use Throwable;

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
        private readonly BusinessPlanSnapshot $snapshots,
        private readonly ExecutiveSummaryEligibility $executiveSummaryEligibility,
    ) {}

    public function firstPass(BusinessPlan $plan, User $actor): PlanAssessment
    {
        $plan = $plan->refresh()->load('sections', 'entrepreneurProfile', 'budgetRunway', 'phases.sections');
        $framework = $this->frameworks->published();
        $criteria = $framework->criteria->values();
        $totalCriteria = $criteria->count();
        $this->updateQueuedFirstPassProgress(
            plan: $plan,
            totalCriteria: $totalCriteria,
            completedCriteria: 0,
            currentCriterion: 'Preparing assessment evidence',
        );

        foreach ($plan->sections as $section) {
            if ($section instanceof PlanSection) {
                $this->documents->ensureScoringClear($section);
            }
        }

        $planSnapshot = $this->snapshots->capture($plan);
        $criterionContexts = $criteria
            ->mapWithKeys(fn (RatingCriterion $criterion): array => [
                (string) $criterion->number => $this->contexts->criterionAssessmentFromSnapshot(
                    snapshot: $planSnapshot,
                    criterion: $criterion,
                ),
            ]);
        $aiScores = $criteria
            ->map(function (RatingCriterion $criterion, int $index) use ($criteria, $criterionContexts, $framework, $plan, $totalCriteria): array {
                $this->updateQueuedFirstPassProgress(
                    plan: $plan,
                    totalCriteria: $totalCriteria,
                    completedCriteria: $index,
                    currentCriterion: sprintf(
                        'Assessing criterion %d of %d: %s',
                        $index + 1,
                        $totalCriteria,
                        $criterion->name,
                    ),
                );

                $score = $this->scoreCriterion(
                    criterion: $criterion,
                    plan: $plan,
                    framework: $framework,
                    planContext: $criterionContexts->get((string) $criterion->number, []),
                );
                $nextCriterion = $criteria->get($index + 1);

                $this->updateQueuedFirstPassProgress(
                    plan: $plan,
                    totalCriteria: $totalCriteria,
                    completedCriteria: $index + 1,
                    currentCriterion: $nextCriterion instanceof RatingCriterion
                        ? sprintf(
                            'Preparing criterion %d of %d: %s',
                            $index + 2,
                            $totalCriteria,
                            $nextCriterion->name,
                        )
                        : 'Saving assessment',
                );

                return $score;
            })
            ->values()
            ->all();
        $documentSupport = $this->documentSupport($plan);
        $weighted = AssessmentScoring::weightedScoreForFramework($framework, $aiScores);

        return DB::transaction(function () use ($plan, $actor, $framework, $aiScores, $documentSupport, $planSnapshot, $totalCriteria, $weighted): PlanAssessment {
            $lockedPlan = BusinessPlan::query()
                ->whereKey($plan->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $latestAssessmentRound = (int) PlanAssessment::query()
                ->where('business_plan_id', $lockedPlan->getKey())
                ->orderByDesc('round')
                ->lockForUpdate()
                ->value('round');
            $pendingRevision = PlanRevision::query()
                ->where('business_plan_id', $lockedPlan->getKey())
                ->where('round', '>', $latestAssessmentRound)
                ->orderBy('round')
                ->lockForUpdate()
                ->get()
                ->first(fn (PlanRevision $revision): bool => data_get($revision->progress_comparison, 'assessment_status') === 'awaiting_advisor_action');
            $round = $pendingRevision instanceof PlanRevision
                ? $pendingRevision->round
                : $latestAssessmentRound + 1;
            $assessment = PlanAssessment::query()->create([
                'business_plan_id' => $lockedPlan->getKey(),
                'round' => max(1, (int) $round),
                'rating_framework_id' => $framework->getKey(),
                'ai_scores' => $aiScores,
                'advisor_scores' => [],
                'mentor_notes' => [],
                'document_support' => $documentSupport,
                'plan_snapshot' => $planSnapshot,
                'overall_grade' => $framework->gradeFor($weighted),
            ]);
            $lockedPlan->forceFill([
                'status' => BusinessPlan::STATUS_ASSESSING,
                'assessment_run_status' => 'completed',
                'assessment_run_total_criteria' => $totalCriteria,
                'assessment_run_completed_criteria' => $totalCriteria,
                'assessment_run_current_criterion' => null,
                'assessment_run_completed_at' => now(),
                'assessment_run_failed_at' => null,
                'assessment_run_failure' => null,
            ])->save();

            $this->audit->record('entrepreneur.plan_first_pass_scored', subject: $assessment, actor: $actor, after: [
                'business_plan_id' => $lockedPlan->getKey(),
                'round' => $assessment->round,
                'criterion_count' => count($aiScores),
                'weighted_score' => $weighted,
                'overall_grade' => $assessment->overall_grade,
                'new_ai_score_generated' => true,
            ]);

            return $assessment->refresh()->load('ratingFramework.criteria');
        });
    }

    public function queueFirstPass(BusinessPlan $plan, User $actor): bool
    {
        return DB::transaction(function () use ($plan, $actor): bool {
            $lockedPlan = BusinessPlan::query()
                ->whereKey($plan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedPlan->assessment_run_status, ['queued', 'running'], true)) {
                return false;
            }

            $lockedPlan->forceFill([
                'status' => BusinessPlan::STATUS_ASSESSING,
                'assessment_run_status' => 'queued',
                'assessment_run_requested_at' => now(),
                'assessment_run_started_at' => null,
                'assessment_run_total_criteria' => null,
                'assessment_run_completed_criteria' => 0,
                'assessment_run_current_criterion' => 'Queued for assessment',
                'assessment_run_completed_at' => null,
                'assessment_run_failed_at' => null,
                'assessment_run_failure' => null,
                'assessment_run_requested_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->record('entrepreneur.plan_assessment_queued', subject: $lockedPlan, actor: $actor, after: [
                'business_plan_id' => $lockedPlan->getKey(),
            ]);

            return true;
        });
    }

    public function markQueuedFirstPassRunning(BusinessPlan $plan, User $actor): ?BusinessPlan
    {
        return DB::transaction(function () use ($plan, $actor): ?BusinessPlan {
            $lockedPlan = BusinessPlan::query()
                ->whereKey($plan->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedPlan instanceof BusinessPlan || $lockedPlan->assessment_run_status !== 'queued') {
                return null;
            }

            $lockedPlan->forceFill([
                'assessment_run_status' => 'running',
                'assessment_run_started_at' => now(),
                'assessment_run_current_criterion' => 'Preparing assessment evidence',
            ])->save();

            $this->audit->record('entrepreneur.plan_assessment_started', subject: $lockedPlan, actor: $actor, after: [
                'business_plan_id' => $lockedPlan->getKey(),
            ]);

            return $lockedPlan->refresh();
        });
    }

    public function markQueuedFirstPassFailed(BusinessPlan $plan, User $actor, Throwable $exception): BusinessPlan
    {
        return DB::transaction(function () use ($plan, $actor, $exception): BusinessPlan {
            $lockedPlan = BusinessPlan::query()
                ->whereKey($plan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPlan->forceFill([
                'assessment_run_status' => 'failed',
                'assessment_run_failed_at' => now(),
                'assessment_run_failure' => Str::limit($exception->getMessage(), 300),
            ])->save();

            $this->audit->record('entrepreneur.plan_assessment_failed', subject: $lockedPlan, actor: $actor, after: [
                'business_plan_id' => $lockedPlan->getKey(),
                'exception' => $exception::class,
            ]);

            return $lockedPlan->refresh();
        });
    }

    private function updateQueuedFirstPassProgress(
        BusinessPlan $plan,
        int $totalCriteria,
        int $completedCriteria,
        ?string $currentCriterion,
    ): void {
        BusinessPlan::query()
            ->whereKey($plan->getKey())
            ->where('assessment_run_status', 'running')
            ->update([
                'assessment_run_total_criteria' => $totalCriteria,
                'assessment_run_completed_criteria' => $completedCriteria,
                'assessment_run_current_criterion' => $currentCriterion,
            ]);
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
        $assessment->loadMissing('ratingFramework.criteria', 'businessPlan.entrepreneurProfile');
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

        $incompleteCriterionNumbers = AssessmentScoring::incompleteCriterionNumbers($assessment);

        if (AssessmentScoring::hasFallbackScores($assessment) || $incompleteCriterionNumbers !== []) {
            $message = $incompleteCriterionNumbers === []
                ? 'This historical round has no valid AI score and cannot be finalised. Run a fresh assessment first.'
                : sprintf(
                    'This assessment is missing valid scores for criterion %s and cannot be finalised. Run a fresh assessment first.',
                    implode(', ', $incompleteCriterionNumbers),
                );

            throw ValidationException::withMessages([
                'assessment' => $message,
            ]);
        }

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
        $assessment = $assessment->refresh()->load('businessPlan.entrepreneurProfile', 'ratingFramework.criteria');
        $this->milestones->awardAssessmentFinalised($assessment);

        $plan = $assessment->businessPlan;
        $profile = $plan?->entrepreneurProfile;
        if ($plan instanceof BusinessPlan && $profile !== null) {
            $assessment = $this->executiveSummaryEligibility->recordAssessmentRevision($assessment, $plan, $profile);
            if ($this->executiveSummaryEligibility->evaluate($plan->refresh(), $profile)['eligible']) {
                GenerateEligibleExecutiveSummary::dispatch((string) $assessment->getKey())->afterCommit();
            }
        }

        return $assessment->refresh();
    }

    /**
     * @param  array<string, mixed>  $planContext
     */
    private function scoreCriterion(
        RatingCriterion $criterion,
        BusinessPlan $plan,
        RatingFramework $framework,
        array $planContext,
    ): array {
        $prompt = new PromptEnvelope(
            id: EntrepreneurPromptRegistry::PLAN_SCORE_CRITERION,
            version: '2026-08-17',
            task: 'Select one rubric band for an entrepreneur business-plan criterion using the complete submitted plan snapshot.',
            body: 'Return JSON only. Set metadata.band to exactly one of exceptional, strong, developing, or needs_work. Do not return a numeric score. Use the criterion descriptors, assess the complete submitted-plan snapshot (including budget evidence), and set text to a concise evidence-based rationale. Do not flatter weak evidence.',
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
                'level' => 'submitted_plan_snapshot',
            ],
            sourceReferences: ['business_plan:'.$plan->getKey(), 'rating_criterion:'.$criterion->getKey()],
        );
        $response = $this->ai->scoreCriterion($prompt);
        $band = $this->bandFromResponse($response);
        $assessmentText = $this->contexts->assessmentText($planContext);

        if ((bool) data_get($response->metadata, 'degraded') || $band === null) {
            $reason = trim((string) data_get($response->metadata, 'unavailable_reason'));
            $reasonSuffix = $reason === '' ? '' : ' Reason: '.Str::limit($reason, 180, '');

            throw new RuntimeException(sprintf(
                'No valid AI rubric band was returned for criterion %d (%s). No assessment round was saved; retry the assessment once the AI service is available.%s',
                $criterion->number,
                $criterion->name,
                $reasonSuffix,
            ));
        }

        $aiScore = $framework->scoreForCriterionBand($band);
        $scoreScale = $framework->criterionBandScores();

        return [
            'criterion_id' => $criterion->getKey(),
            'criterion_number' => $criterion->number,
            'criterion_name' => $criterion->name,
            'score' => $aiScore,
            'score_source' => 'ai_assessment',
            'rationale' => $this->rationaleFromResponse($response, $aiScore),
            'attributions' => [
                ...$response->attributions,
                [
                    'claim' => 'Criterion band derived from the complete submitted business-plan snapshot.',
                    'source_reference' => 'business_plan:'.$plan->getKey(),
                ],
            ],
            'model' => $response->model,
            'metadata' => [
                ...$response->metadata,
                'ai_score' => $aiScore,
                'score_band' => $band,
                'score_scale' => $scoreScale,
                'score_source' => 'ai_assessment',
                'scoring_method' => 'calibrated_band_v1',
                'uncertainty' => $response->uncertainty->value,
                'context_characters' => Str::length($assessmentText),
                'context_hash' => hash('sha256', $assessmentText),
                'evidence_mode' => (string) ($planContext['evidence_mode'] ?? 'selected_plan_excerpts'),
                'evidence_section_count' => count($planContext['full_plan_sections'] ?? []),
                'budget_evidence_included' => is_array($planContext['budget_evidence'] ?? null),
                'source_sections' => $this->sourceSectionsFromContext($planContext),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $planContext
     * @return array<int, array{section_id:string,title:string,requirement_key:string|null,updated_at:string|null,body_excerpt:string}>
     */
    private function sourceSectionsFromContext(array $planContext): array
    {
        $sections = is_array($planContext['criterion_focus_sections'] ?? null)
            ? $planContext['criterion_focus_sections']
            : (is_array($planContext['full_plan_sections'] ?? null)
                ? $planContext['full_plan_sections']
            : [
                ...($planContext['relevant_sections'] ?? []),
                ...($planContext['supporting_section_summaries'] ?? []),
            ]);

        return collect($sections)
            ->map(fn (array $section): array => [
                'section_id' => (string) ($section['section_id'] ?? ''),
                'title' => (string) ($section['title'] ?? ''),
                'requirement_key' => isset($section['requirement_key']) ? (string) $section['requirement_key'] : null,
                'updated_at' => isset($section['updated_at']) ? (string) $section['updated_at'] : null,
                'body_excerpt' => Str::limit((string) ($section['body'] ?? $section['body_excerpt'] ?? ''), 700),
            ])
            ->filter(fn (array $section): bool => $section['section_id'] !== '' || $section['title'] !== '')
            ->values()
            ->all();
    }

    private function bandFromResponse(AiResponse $response): ?string
    {
        $candidate = data_get($response->metadata, 'band')
            ?? data_get($response->metadata, 'score_band');

        if (! is_string($candidate)) {
            return null;
        }

        $band = strtolower(trim(str_replace([' ', '-'], '_', $candidate)));

        return array_key_exists($band, RatingFramework::DEFAULT_CRITERION_BAND_SCORES)
            ? $band
            : null;
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
