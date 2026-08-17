<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Jobs\RunEntrepreneurPlanAssessment;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\LearningUpdate;
use App\Models\PlanAssessment;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Entrepreneurs\Assessment;
use App\Services\Entrepreneurs\AssessmentFeedback;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Entrepreneurs\PlanBuilder;
use App\Support\RequestContext;
use Database\Seeders\FoundingRatingFrameworkValuesSeeder;
use Database\Seeders\RatingFrameworkSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\MakesIdeaReviewEligible;
use Tests\TestCase;

final class AssessmentTest extends TestCase
{
    use MakesIdeaReviewEligible, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RatingFrameworkSeeder::class);
        $this->seed(FoundingRatingFrameworkValuesSeeder::class);
        $this->app->instance(AiClient::class, new StructuredScoreAiClient(70));
        app(RequestContext::class)->apply('system', []);
    }

    public function test_first_pass_scores_all_current_framework_criteria(): void
    {
        [$advisor, $plan] = $this->plan();

        $assessment = app(Assessment::class)->firstPass($plan, $advisor);

        $this->assertInstanceOf(PlanAssessment::class, $assessment);
        $this->assertCount(12, $assessment->ai_scores);
        $this->assertSame(2, $assessment->ratingFramework->version);
        $this->assertContains($assessment->overall_grade, ['exceptional', 'strong', 'developing', 'needs_work']);
        $this->assertSame(BusinessPlan::STATUS_ASSESSING, $plan->refresh()->status);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.plan_first_pass_scored',
            'subject_id' => $assessment->id,
        ]);
    }

    public function test_first_pass_uses_server_calibrated_band_score_when_supplied(): void
    {
        $this->app->instance(AiClient::class, new StructuredScoreAiClient(91));
        [$advisor, $plan] = $this->plan('structured-score-founder@example.test');

        $assessment = app(Assessment::class)->firstPass($plan, $advisor);

        $this->assertSame(100, data_get($assessment->ai_scores, '0.score'));
        $this->assertSame('exceptional', data_get($assessment->ai_scores, '0.metadata.score_band'));
        $this->assertEqualsCanonicalizing(
            ['exceptional' => 100, 'strong' => 80, 'developing' => 55, 'needs_work' => 30],
            data_get($assessment->ai_scores, '0.metadata.score_scale'),
        );
        $this->assertSame('ai_assessment', data_get($assessment->ai_scores, '0.score_source'));
        $this->assertSame('exceptional', $assessment->overall_grade);
    }

    public function test_first_pass_scores_the_complete_submitted_snapshot_including_budget_evidence(): void
    {
        $ai = new CapturingScoreAiClient(70);
        $this->app->instance(AiClient::class, $ai);
        [$advisor, $plan] = $this->plan('complete-snapshot-assessment@example.test');
        app(PlanBuilder::class)->upsertSection(
            plan: $plan->refresh(),
            phaseKey: 'market',
            key: 'market-complete-snapshot-evidence',
            title: 'Complete snapshot evidence',
            body: 'This evidence must be present for every criterion, not only the market criterion.',
            actor: $advisor,
            metadata: ['requirement_key' => 'industry-context'],
        );
        EntrepreneurBudget::query()->create([
            'business_plan_id' => $plan->getKey(),
            'status' => EntrepreneurBudget::STATUS_COMPLETE,
            'forecast_years' => 3,
            'assumptions' => ['pricing_basis' => 'Snapshot budget pricing evidence'],
            'computed' => ['runway_months' => 12],
        ]);

        $assessment = app(Assessment::class)->firstPass($plan->refresh(), $advisor);
        $promptInput = json_encode(
            array_map(fn (PromptEnvelope $prompt): array => $prompt->input, $ai->scorePrompts),
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame(55, data_get($assessment->ai_scores, '0.score'));
        $this->assertSame('developing', data_get($assessment->ai_scores, '0.metadata.score_band'));
        $this->assertSame('calibrated_band_v1', data_get($assessment->ai_scores, '0.metadata.scoring_method'));
        $this->assertSame('complete_submitted_plan_snapshot', data_get($assessment->ai_scores, '0.metadata.evidence_mode'));
        $this->assertTrue((bool) data_get($assessment->ai_scores, '0.metadata.budget_evidence_included'));
        $this->assertStringContainsString('This evidence must be present for every criterion', $promptInput);
        $this->assertStringContainsString('Snapshot budget pricing evidence', $promptInput);
        $this->assertSame('Snapshot budget pricing evidence', data_get($assessment->plan_snapshot, 'budget.assessment_evidence.assumptions.pricing_basis'));

        $profile = $plan->entrepreneurProfile()->firstOrFail();
        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.assessments.show', [$profile, $assessment]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessment.scoring.is_calibrated', true)
                ->where('assessment.scoring.uses_complete_snapshot_evidence', true)
                ->where('assessment.criteria.0.score_band', 'developing')
                ->where('assessment.criteria.0.contribution', 4.4)
                ->where('assessment.evidence_audit.includes_budget_evidence', true)
                ->where('assessment.evidence_audit.section_count', 6)
            );
    }

    public function test_super_admin_assessment_from_the_workspace_queues_a_durable_background_run(): void
    {
        [$advisor, $plan] = $this->plan('workspace-assessment-founder@example.test');
        $profile = $plan->entrepreneurProfile()->firstOrFail();
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);
        Queue::fake();

        $response = $this->actingAsMfa($admin)
            ->post(route('advisor.entrepreneurs.plans.assessments.store', [
                'entrepreneurProfile' => $profile,
                'businessPlan' => $plan,
            ]));

        $response->assertRedirect(route('advisor.entrepreneurs.show', $profile, absolute: false));
        Queue::assertPushed(
            RunEntrepreneurPlanAssessment::class,
            fn (RunEntrepreneurPlanAssessment $job): bool => $job->businessPlanId === (string) $plan->getKey()
                && $job->advisorId === (int) $admin->getKey(),
        );
        $this->assertSame('queued', $plan->refresh()->assessment_run_status);
        $this->assertDatabaseCount('plan_assessments', 0);

        $this->actingAsMfa($admin)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/entrepreneurs/Show')
                ->where('entrepreneur.latest_plan.assessment_count', 0)
                ->where('entrepreneur.latest_plan.assessment_run.status', 'queued')
                ->where('entrepreneur.latest_plan.can_assess', false)
            );

        (new RunEntrepreneurPlanAssessment((string) $plan->getKey(), (int) $admin->getKey()))
            ->handle(app(RequestContext::class), app(Assessment::class));

        $assessment = PlanAssessment::query()
            ->where('business_plan_id', $plan->getKey())
            ->firstOrFail();

        $this->assertSame(EntrepreneurStage::ASSESSMENT, $profile->refresh()->stage);
        $this->assertSame('completed', $plan->refresh()->assessment_run_status);

        $this->actingAsMfa($admin)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/entrepreneurs/Show')
                ->where('entrepreneur.latest_plan.assessment_count', 1)
                ->where('entrepreneur.latest_plan.latest_round', 1)
                ->where('entrepreneur.latest_plan.latest_assessment.id', $assessment->id)
            );
    }

    public function test_workspace_reassessment_uses_resubmitted_plan_content_and_advances_latest_round(): void
    {
        $ai = new CapturingScoreAiClient(82);
        $this->app->instance(AiClient::class, $ai);
        [$advisor, $plan] = $this->plan('workspace-reassessment-founder@example.test');
        $profile = $plan->entrepreneurProfile()->firstOrFail();
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        app(PlanBuilder::class)->upsertSection(
            plan: $plan->refresh(),
            phaseKey: 'market',
            key: 'market-demand',
            title: 'Market demand',
            body: 'Original first-round evidence only mentions vague market interest and does not name paid pilots.',
            actor: $advisor,
        );
        $first = app(Assessment::class)->firstPass($plan->refresh()->load('sections'), $advisor);

        $updatedSection = app(PlanBuilder::class)->upsertSection(
            plan: $plan->refresh(),
            phaseKey: 'market',
            key: 'market-demand',
            title: 'Market demand',
            body: 'Revised second-round evidence names six paid pilots, updated competitor pricing, and current demand signals from the founder resubmission.',
            actor: $advisor,
        );
        $plan->forceFill(['status' => BusinessPlan::STATUS_SUBMITTED])->save();
        $ai->scorePrompts = [];
        Queue::fake();

        $response = $this->actingAsMfa($admin)
            ->post(route('advisor.entrepreneurs.plans.assessments.store', [
                'entrepreneurProfile' => $profile,
                'businessPlan' => $plan,
            ]));

        Queue::assertPushed(RunEntrepreneurPlanAssessment::class);
        $this->assertSame('queued', $plan->refresh()->assessment_run_status);
        (new RunEntrepreneurPlanAssessment((string) $plan->getKey(), (int) $admin->getKey()))
            ->handle(app(RequestContext::class), app(Assessment::class));

        $latest = PlanAssessment::query()
            ->where('business_plan_id', $plan->getKey())
            ->orderByDesc('round')
            ->firstOrFail();
        $capturedPromptInput = json_encode(
            array_map(fn (PromptEnvelope $prompt): array => $prompt->input, $ai->scorePrompts),
            JSON_THROW_ON_ERROR
        );
        $sourceSectionIds = collect($latest->ai_scores)
            ->flatMap(fn (array $score): array => collect(data_get($score, 'metadata.source_sections', []))
                ->pluck('section_id')
                ->all())
            ->unique()
            ->values()
            ->all();

        $response->assertRedirect(route('advisor.entrepreneurs.show', $profile, absolute: false));
        $this->assertSame(2, PlanAssessment::query()->where('business_plan_id', $plan->getKey())->count());
        $this->assertSame(2, $latest->round);
        $this->assertNotSame($first->getKey(), $latest->getKey());
        $this->assertStringContainsString('Revised second-round evidence names six paid pilots', $capturedPromptInput);
        $this->assertStringNotContainsString('Original first-round evidence only mentions vague market interest', $capturedPromptInput);
        $this->assertContains($updatedSection->getKey(), $sourceSectionIds);
        $snapshotJson = json_encode($latest->plan_snapshot, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Revised second-round evidence names six paid pilots', $snapshotJson);
        $this->assertStringNotContainsString('Original first-round evidence only mentions vague market interest', $snapshotJson);

        $this->actingAsMfa($admin)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/entrepreneurs/Show')
                ->where('entrepreneur.latest_plan.assessment_count', 2)
                ->where('entrepreneur.latest_plan.latest_round', 2)
                ->where('entrepreneur.latest_plan.latest_assessment.id', $latest->id)
                ->where('entrepreneur.latest_plan.assessment_history.0.round', 2)
                ->where('entrepreneur.latest_plan.assessment_history.0.score_delta', 0)
                ->where('entrepreneur.latest_plan.assessment_history.0.score_source_summary', 'Calibrated rubric-band assessment against the complete submitted plan snapshot, including budget evidence.')
                ->where('entrepreneur.latest_plan.assessment_history.0.snapshot_available', true)
                ->where('entrepreneur.latest_plan.assessment_history.0.snapshot_note', 'Submitted-plan snapshot captured for this assessment round.')
                ->where('entrepreneur.latest_plan.assessment_history.0.plan_snapshot_url', route('advisor.entrepreneurs.assessments.plan-preview', [$profile, $latest], absolute: false))
            );

        $this->actingAsMfa($admin)
            ->get(route('advisor.entrepreneurs.assessments.show', [$profile, $latest]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/entrepreneur/Assessment')
                ->where('assessment.basis.label', 'Resubmitted business plan')
                ->where('assessment.basis.plan_snapshot_available', true)
                ->where('assessment.basis.plan_snapshot_url', route('advisor.entrepreneurs.assessments.plan-preview', [$profile, $latest], absolute: false))
                ->where('assessment.criteria.0.source_label', 'Round 2 automated score')
                ->where('assessment.explanation', fn (string $value): bool => str_contains($value, 'assessment round 2')
                    && str_contains($value, 'server-converted rubric band')
                    && ! str_contains(strtolower($value), 'first-pass'))
            );
    }

    public function test_queued_assessment_records_a_failure_without_creating_a_partial_round(): void
    {
        [$advisor, $plan] = $this->plan('queued-assessment-failure@example.test');
        $this->app->instance(AiClient::class, new FailingScoreAiClient);

        $this->assertTrue(app(Assessment::class)->queueFirstPass($plan, $advisor));

        (new RunEntrepreneurPlanAssessment((string) $plan->getKey(), (int) $advisor->getKey()))
            ->handle(app(RequestContext::class), app(Assessment::class));

        $this->assertSame('failed', $plan->refresh()->assessment_run_status);
        $this->assertStringContainsString('provider did not respond', (string) $plan->assessment_run_failure);
        $this->assertDatabaseCount('plan_assessments', 0);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.plan_assessment_failed',
            'subject_id' => $plan->getKey(),
        ]);
    }

    public function test_degraded_ai_scoring_fails_without_saving_a_formula_based_assessment(): void
    {
        [$advisor, $plan] = $this->plan('degraded-assessment@example.test');
        $this->app->instance(AiClient::class, new FakeAiClient);

        $this->assertTrue(app(Assessment::class)->queueFirstPass($plan, $advisor));

        (new RunEntrepreneurPlanAssessment((string) $plan->getKey(), (int) $advisor->getKey()))
            ->handle(app(RequestContext::class), app(Assessment::class));

        $this->assertSame('failed', $plan->refresh()->assessment_run_status);
        $this->assertStringContainsString('No valid AI rubric band was returned', (string) $plan->assessment_run_failure);
        $this->assertDatabaseCount('plan_assessments', 0);
    }

    public function test_reassessment_generates_fresh_scores_when_the_submitted_plan_has_not_changed(): void
    {
        $firstAi = new CapturingScoreAiClient(82);
        $this->app->instance(AiClient::class, $firstAi);
        [$advisor, $plan] = $this->plan('stable-reassessment-founder@example.test');

        $first = app(Assessment::class)->firstPass($plan, $advisor);
        $freshAi = new CapturingScoreAiClient(95);
        $this->app->instance(AiClient::class, $freshAi);

        $second = app(Assessment::class)->firstPass($plan->refresh(), $advisor);

        $this->assertSame(2, $second->round);
        $this->assertSame(80, data_get($first->ai_scores, '0.score'));
        $this->assertSame(100, data_get($second->ai_scores, '0.score'));
        $this->assertCount(12, $freshAi->scorePrompts);
        $this->assertTrue(collect($second->ai_scores)->every(
            fn (array $score): bool => $score['score_source'] === 'ai_assessment'
                && ! data_get($score, 'metadata.reuse_basis'),
        ));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.assessments.show', [$plan->entrepreneurProfile()->firstOrFail(), $second]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessment.criteria.0.source_label', 'Round 2 automated score')
                ->where('assessment.requires_full_reassessment', false)
                ->where('assessment.explanation', fn (string $value): bool => str_contains($value, 'server-converted rubric band'))
            );
    }

    public function test_historical_fallback_scores_are_unavailable_and_do_not_affect_score_movement(): void
    {
        $this->app->instance(AiClient::class, new StructuredScoreAiClient(50));
        [$advisor, $plan] = $this->plan('fallback-history-founder@example.test');
        $profile = $plan->entrepreneurProfile()->firstOrFail();

        app(Assessment::class)->firstPass($plan, $advisor);
        $fallback = app(Assessment::class)->firstPass($plan->refresh(), $advisor);
        $fallback->forceFill([
            'ai_scores' => collect($fallback->ai_scores)
                ->map(function (array $score): array {
                    $metadata = is_array($score['metadata'] ?? null) ? $score['metadata'] : [];

                    return [
                        ...$score,
                        'score' => 95,
                        'score_source' => 'deterministic_fallback',
                        'metadata' => [
                            ...$metadata,
                            'score_source' => 'deterministic_fallback',
                        ],
                    ];
                })
                ->values()
                ->all(),
        ])->save();
        $third = app(Assessment::class)->firstPass($plan->refresh(), $advisor);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('entrepreneur.latest_plan.assessment_history.0.round', $third->round)
                ->where('entrepreneur.latest_plan.assessment_history.0.weighted_score', 30)
                ->where('entrepreneur.latest_plan.assessment_history.0.score_delta', 0)
                ->where('entrepreneur.latest_plan.assessment_history.1.round', $fallback->round)
                ->where('entrepreneur.latest_plan.assessment_history.1.automated_score_available', false)
                ->where('entrepreneur.latest_plan.assessment_history.1.weighted_score', null)
                ->where('entrepreneur.latest_plan.assessment_history.1.overall_grade', null)
                ->where('entrepreneur.latest_plan.assessment_history.1.score_source_summary', 'Invalid automated result: no AI score was returned. Retained for audit only and excluded from progression.'),
            );

        try {
            app(Assessment::class)->finalise($fallback->refresh(), $advisor);
            $this->fail('Expected an invalid fallback assessment to be blocked from finalisation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assessment', $exception->errors());
        }
    }

    public function test_carried_forward_historical_scores_are_flagged_for_a_fresh_assessment(): void
    {
        $ai = new CapturingScoreAiClient(82);
        $this->app->instance(AiClient::class, $ai);
        [$advisor, $plan] = $this->plan('legacy-partial-reuse-founder@example.test');

        app(Assessment::class)->firstPass($plan, $advisor);
        $assessment = app(Assessment::class)->firstPass($plan->refresh(), $advisor);
        $assessment->forceFill([
            'ai_scores' => collect($assessment->ai_scores)
                ->map(function (array $score): array {
                    $metadata = is_array($score['metadata'] ?? null) ? $score['metadata'] : [];

                    return [
                        ...$score,
                        'score_source' => 'reused_identical_context',
                        'metadata' => [
                            ...$metadata,
                            'score_source' => 'reused_identical_context',
                            'reused_from_round' => 1,
                            'reuse_basis' => 'submitted_plan_snapshot',
                        ],
                    ];
                })
                ->values()
                ->all(),
        ])->save();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.assessments.show', [$plan->entrepreneurProfile()->firstOrFail(), $assessment]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessment.requires_full_reassessment', true)
                ->where('assessment.criteria.0.source_label', fn (string $value): bool => str_contains($value, 'carried forward'))
                ->where('assessment.explanation', fn (string $value): bool => str_contains($value, 'carried forward')),
            );
    }

    public function test_advisor_adjustment_requires_note_and_queues_governed_learning(): void
    {
        [$advisor, $plan] = $this->plan('adjustment-founder@example.test');
        $assessment = app(Assessment::class)->firstPass($plan, $advisor);

        try {
            app(Assessment::class)->adjustScore($assessment, 1, 72, ' ', $advisor);
            $this->fail('Expected score adjustment without note to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('note', $e->errors());
        }

        $adjusted = app(Assessment::class)->adjustScore($assessment, 1, 72, 'Advisor saw stronger evidence in the founder interview.', $advisor);

        $this->assertSame(72, data_get($adjusted->advisor_scores, '1.score'));
        $this->assertSame('Advisor saw stronger evidence in the founder interview.', data_get($adjusted->advisor_scores, '1.note'));
        $this->assertDatabaseHas('learning_updates', [
            'status' => LearningUpdate::STATUS_DETECTED,
            'summary' => 'Advisor adjusted an entrepreneur plan score; review calibration.',
        ]);
        $update = LearningUpdate::query()->latest()->firstOrFail();
        $this->assertFalse((bool) data_get($update->proposed_change, 'automatic_application'));
    }

    public function test_private_advisory_note_is_not_visible_to_entrepreneur(): void
    {
        [$advisor, $plan] = $this->plan('notes-founder@example.test');
        $assessment = app(Assessment::class)->firstPass($plan, $advisor);

        $withNotes = app(Assessment::class)->setMentorNotes(
            assessment: $assessment,
            sectionNotes: ['market-demand' => 'Clarify customer evidence.'],
            overallVisible: 'Good progress; tighten the evidence.',
            privateAdvisory: 'Founder confidence is fragile; handle directly.',
            advisor: $advisor,
        );

        $visible = app(Assessment::class)->entrepreneurVisibleNotes($withNotes);

        $this->assertSame('Good progress; tighten the evidence.', $visible['overall_visible']);
        $this->assertArrayNotHasKey('private_advisory', $visible);
        $this->assertSame('Founder confidence is fragile; handle directly.', data_get($withNotes->mentor_notes, 'private_advisory'));
    }

    public function test_assessment_feedback_draft_uses_the_actual_scored_priorities(): void
    {
        [$advisor, $plan] = $this->plan('assessment-feedback-draft@example.test');
        $assessment = app(Assessment::class)->firstPass($plan, $advisor);
        $feedbacks = app(AssessmentFeedback::class);

        $feedback = $feedbacks->draft($assessment);
        $priorities = $feedbacks->priorities($assessment);
        $reply = $feedbacks->proposedReply($plan->entrepreneurProfile()->firstOrFail(), $assessment);

        $this->assertCount(3, $priorities);
        $this->assertArrayHasKey('what_is_missing', $priorities[0]);
        $this->assertArrayHasKey('what_to_add_or_change', $priorities[0]);
        $this->assertArrayHasKey('where_in_plan', $priorities[0]);
        $this->assertStringContainsString('Assessment completed:', $feedback);
        $this->assertStringContainsString('Assessment finding:', $feedback);
        $this->assertStringContainsString('Suggested next step:', $feedback);
        $this->assertStringContainsString('Plan sections reviewed:', $feedback);
        $this->assertStringContainsString('Dear Assessment,', $reply);
        $this->assertStringContainsString('You have made progress', $reply);
        $this->assertStringContainsString('Assessment finding:', $reply);
        $this->assertStringNotContainsString('What is missing:', $reply);
        $this->assertStringNotContainsString('Assessment completed:', $reply);
    }

    public function test_assessment_feedback_reply_uses_plain_language_without_truncated_ai_rationale(): void
    {
        [$advisor, $plan] = $this->plan('assessment-feedback-plain-language@example.test');
        $assessment = app(Assessment::class)->firstPass($plan, $advisor);
        $assessment->forceFill([
            'ai_scores' => collect($assessment->ai_scores)
                ->map(fn (array $row, int $index): array => [
                    ...$row,
                    'score' => $index < 3 ? 35 + $index : 88,
                    'rationale' => 'The section is directionally aware but materially underdeveloped for launch decision-making and anchors the target cust...',
                ])
                ->all(),
        ])->save();

        $feedbacks = app(AssessmentFeedback::class);
        $reply = $feedbacks->proposedReply($plan->entrepreneurProfile()->firstOrFail(), $assessment->refresh());
        $replyLower = strtolower($reply);

        $this->assertStringContainsString('You do not need to start again.', $reply);
        $this->assertStringContainsString('Please use the assessment findings and suggested next steps below:', $reply);
        $this->assertStringNotContainsString('...', $reply);
        $this->assertStringNotContainsString('directionally', $replyLower);
        $this->assertStringNotContainsString('materially underdeveloped', $replyLower);
        $this->assertStringNotContainsString('launch decision-making', $replyLower);
        $this->assertTrue($feedbacks->isLegacyReply('The IP section is directionally aware but materially underdeveloped for launch decision-making...'));
    }

    public function test_assessment_feedback_draft_shows_round_movement_and_current_source_evidence(): void
    {
        $this->app->instance(AiClient::class, new CapturingScoreAiClient(82));
        [$advisor, $plan] = $this->plan('assessment-feedback-evidence@example.test');
        $first = app(Assessment::class)->firstPass($plan, $advisor);
        $first->forceFill([
            'ai_scores' => $this->scoresWithOverrides($first, [
                4 => 34,
                8 => 32,
                9 => 38,
            ], 88),
        ])->save();

        app(PlanBuilder::class)->upsertSection(
            plan: $plan->refresh(),
            phaseKey: 'legal_operations',
            key: 'founder-legal_operations-intellectual-property',
            title: 'Intellectual property',
            body: 'Updated second-round IP register lists the Drawer Full of Giants brand, facilitation methods, content library, contracts, copyright ownership, and protection steps.',
            actor: $advisor,
            metadata: ['requirement_key' => 'intellectual-property'],
        );
        app(PlanBuilder::class)->upsertSection(
            plan: $plan->refresh(),
            phaseKey: 'market',
            key: 'founder-market-industry-context',
            title: 'Industry and customer demand',
            body: 'Updated second-round industry evidence names six paid pilots, current competitor pricing, customer interviews, and demand signals from the founder resubmission.',
            actor: $advisor,
            metadata: ['requirement_key' => 'industry-context'],
        );
        app(PlanBuilder::class)->upsertSection(
            plan: $plan->refresh(),
            phaseKey: 'strategy',
            key: 'founder-strategy-goals-objectives',
            title: 'Goals and objectives',
            body: 'Updated second-round goals include dated launch milestones, owners, success measures, and the decision each milestone will support.',
            actor: $advisor,
            metadata: ['requirement_key' => 'goals-objectives'],
        );

        $second = app(Assessment::class)->firstPass($plan->refresh(), $advisor);
        $second->forceFill([
            'ai_scores' => $this->scoresWithOverrides($second, [
                4 => 36,
                8 => 45,
                9 => 42,
            ], 88),
        ])->save();

        $feedback = app(AssessmentFeedback::class)->draft($second->refresh());

        $this->assertStringContainsString('Assessment finding: AI rationale tied to the supplied resubmitted plan evidence.', $feedback);
        $this->assertStringContainsString('Round movement: previous round 1 was 32.0/100; current round is 45.0/100 (+13.0).', $feedback);
        $this->assertStringContainsString('Scored from the complete submitted-plan snapshot:', $feedback);
        $this->assertStringContainsString('Updated second-round IP register', $feedback);
        $this->assertStringNotContainsString('What is missing:', $feedback);
        $this->assertStringNotContainsString('target cust...', $feedback);
    }

    public function test_advisor_can_save_and_send_assessment_feedback_to_the_founder(): void
    {
        [$advisor, $plan] = $this->plan('assessment-feedback-founder@example.test');
        $profile = $plan->entrepreneurProfile()->firstOrFail();
        $assessment = app(Assessment::class)->firstPass($plan, $advisor);
        $feedback = 'Strengthen the financial assumptions and add customer evidence before the next assessment.';
        $proposedReply = "Dear Assessment,\n\nThank you for the work on your plan. Please strengthen the financial assumptions and add customer evidence before the next assessment.";

        $response = $this->actingAsMfa($advisor)
            ->patch(route('advisor.entrepreneurs.assessments.feedback.update', [$profile, $assessment]), [
                'feedback' => $feedback,
                'proposed_reply' => $proposedReply,
                'send_to_founder' => true,
            ]);

        $response->assertRedirect(route('advisor.entrepreneurs.assessments.show', [$profile, $assessment], absolute: false));

        $notes = $assessment->refresh()->mentor_notes;
        $this->assertSame($feedback, data_get($notes, 'advisor_feedback'));
        $this->assertSame($feedback, data_get($notes, 'overall_visible'));
        $this->assertSame($proposedReply, data_get($notes, 'proposed_reply'));
        $this->assertNotNull(data_get($notes, 'feedback_sent_at'));
        $this->assertSame($assessment->getKey(), data_get($notes, 'feedback_snapshot.source.plan_assessment_id'));
        $this->assertCount(3, data_get($notes, 'feedback_snapshot.priorities'));
        $this->assertNotNull(data_get($notes, 'feedback_snapshot.suggested_feedback.sha256'));
        $this->assertTrue(data_get($notes, 'feedback_snapshot.advisor_edits.feedback_changed_from_suggestion'));
        $this->assertTrue(data_get($notes, 'feedback_snapshot.advisor_edits.proposed_reply_changed_from_suggestion'));
        $this->assertSame(BusinessPlan::STATUS_REVISING, $plan->refresh()->status);
        $this->assertSame(EntrepreneurStage::REVISING, $profile->refresh()->stage);
        $this->assertDatabaseHas('message_threads', [
            'entrepreneur_profile_id' => $profile->getKey(),
            'subject' => 'Business plan assessment feedback',
        ]);
        $this->assertDatabaseHas('messages', [
            'body' => $proposedReply,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.plan_assessment_feedback_sent',
            'subject_id' => $assessment->getKey(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.plan_revision_opened',
            'subject_id' => $plan->getKey(),
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.assessments.show', [$profile, $assessment]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/entrepreneur/Assessment')
                ->where('advisorFeedback.feedback', $feedback)
                ->where('advisorFeedback.proposed_reply', $proposedReply)
                ->missing('assessment.mentor_notes.feedback_snapshot')
            );
    }

    public function test_saved_assessment_feedback_stays_private_until_it_is_sent(): void
    {
        [$advisor, $plan] = $this->plan('assessment-feedback-draft-founder@example.test');
        $assessment = app(Assessment::class)->firstPass($plan, $advisor);

        $saved = app(Assessment::class)->saveAdvisorFeedback(
            assessment: $assessment,
            feedback: 'Keep this assessment feedback private until the advisor is ready to send it.',
            proposedReply: 'Dear Assessment, this is a private draft.',
            sentToFounder: false,
            advisor: $advisor,
        );

        $visible = app(Assessment::class)->entrepreneurVisibleNotes($saved);

        $this->assertArrayNotHasKey('advisor_feedback', $visible);
        $this->assertArrayNotHasKey('proposed_reply', $visible);
        $this->assertArrayNotHasKey('feedback_snapshot', $visible);
        $this->assertArrayNotHasKey('overall_visible', $visible);
    }

    public function test_criteria_are_hidden_until_assessment_is_finalised(): void
    {
        [$advisor, $plan] = $this->plan('visibility-founder@example.test');
        $assessment = app(Assessment::class)->firstPass($plan, $advisor);

        $this->assertFalse(app(Assessment::class)->criteriaVisible($plan));

        app(Assessment::class)->finalise($assessment, $advisor);

        $this->assertTrue(app(Assessment::class)->criteriaVisible($plan));
        $this->assertSame(BusinessPlan::STATUS_FINALISED, $plan->refresh()->status);
    }

    /**
     * @param  array<int, int>  $overrides
     * @return array<int, array<string, mixed>>
     */
    private function scoresWithOverrides(PlanAssessment $assessment, array $overrides, int $defaultScore): array
    {
        return collect($assessment->ai_scores)
            ->map(fn (array $row): array => [
                ...$row,
                'score' => $overrides[(int) $row['criterion_number']] ?? $defaultScore,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{0: User, 1: BusinessPlan}
     */
    private function plan(string $email = 'assessment-founder@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Assessment Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'concept_summary' => 'Assessment concept for regional retail services.',
        ]);
        $validation = app(IdeaValidationService::class)->evaluate($profile, [
            'problem' => 'Retail service operators need clearer goals and legal operating decisions.',
            'target_customer' => 'Regional retail service owners.',
            'solution' => 'A guided plan with market, legal, culture, and financial milestones.',
            'value_proposition' => 'The owner focuses effort and reduces launch risk.',
            'demand_signal' => 'Pilot interviews and customer evidence are complete.',
            'revenue_model' => 'Subscription revenue with onboarding support.',
        ], $advisor);
        app(IdeaValidationService::class)->passAdvisorGate($this->completedIdeaReview($validation), $advisor, 'Ready for scoring.');
        $plan = app(PlanBuilder::class)->start($profile, $advisor);

        foreach ([
            ['market', 'market-demand', 'Market demand', 'The industry, location, customer segment, competitors, demand, revenue, and goals are described with pilot evidence.'],
            ['strategy', 'strategy-goals', 'Strategy goals', 'The mission and vision statement, culture, goals and objectives, and unique success factors are connected to milestones.'],
            ['legal_operations', 'legal-environment', 'Legal environment', 'The legal environment, intellectual property, contracts, privacy duties, and means of doing business are listed.'],
            ['financial', 'financial-model', 'Financial model', 'The plan explains pricing, cash needs, margin, revenue, and support required to operate.'],
        ] as [$phase, $key, $title, $body]) {
            app(PlanBuilder::class)->upsertSection(
                plan: $plan,
                phaseKey: $phase,
                key: $key,
                title: $title,
                body: $body,
                actor: $advisor,
            );
        }

        return [$advisor, $plan->refresh()->load('sections')];
    }
}

final class StructuredScoreAiClient implements AiClient
{
    public function __construct(private readonly int $score) {}

    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt);
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt);
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt, ['band' => $this->bandForScore()]);
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt);
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function response(PromptEnvelope $prompt, array $metadata = []): AiResponse
    {
        return new AiResponse(
            text: 'AI rationale tied to the supplied framework evidence.',
            attributions: [
                [
                    'claim' => 'AI score derived from current business plan draft.',
                    'source_reference' => 'test:structured-score-ai-client',
                ],
            ],
            uncertainty: Uncertainty::Low,
            biasSignals: [],
            model: 'structured-score-ai-client',
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            tokensIn: 1,
            tokensOut: 1,
            metadata: $metadata,
        );
    }

    private function bandForScore(): string
    {
        return match (true) {
            $this->score >= 90 => 'exceptional',
            $this->score >= 75 => 'strong',
            $this->score >= 60 => 'developing',
            default => 'needs_work',
        };
    }
}

final class CapturingScoreAiClient implements AiClient
{
    /**
     * @var array<int, PromptEnvelope>
     */
    public array $scorePrompts = [];

    public function __construct(private readonly int $score) {}

    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt);
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt);
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        $this->scorePrompts[] = $prompt;

        return $this->response($prompt, ['band' => $this->bandForScore()]);
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt);
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function response(PromptEnvelope $prompt, array $metadata = []): AiResponse
    {
        return new AiResponse(
            text: 'AI rationale tied to the supplied resubmitted plan evidence.',
            attributions: [
                [
                    'claim' => 'AI score derived from supplied plan context.',
                    'source_reference' => 'test:capturing-score-ai-client',
                ],
            ],
            uncertainty: Uncertainty::Low,
            biasSignals: [],
            model: 'capturing-score-ai-client',
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            tokensIn: 1,
            tokensOut: 1,
            metadata: $metadata,
        );
    }

    private function bandForScore(): string
    {
        return match (true) {
            $this->score >= 90 => 'exceptional',
            $this->score >= 75 => 'strong',
            $this->score >= 60 => 'developing',
            default => 'needs_work',
        };
    }
}

final class FailingScoreAiClient implements AiClient
{
    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        throw new \RuntimeException('AI provider did not respond while scoring the plan.');
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        throw new \RuntimeException('AI provider did not respond while scoring the plan.');
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        throw new \RuntimeException('AI provider did not respond while scoring the plan.');
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        throw new \RuntimeException('AI provider did not respond while scoring the plan.');
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        throw new \RuntimeException('AI provider did not respond while scoring the plan.');
    }
}
