<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
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
        $this->app->bind(AiClient::class, FakeAiClient::class);
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

    public function test_first_pass_uses_structured_ai_score_when_supplied(): void
    {
        $this->app->instance(AiClient::class, new StructuredScoreAiClient(91));
        [$advisor, $plan] = $this->plan('structured-score-founder@example.test');

        $assessment = app(Assessment::class)->firstPass($plan, $advisor);

        $this->assertSame(91, data_get($assessment->ai_scores, '0.score'));
        $this->assertSame('ai_assessment', data_get($assessment->ai_scores, '0.score_source'));
        $this->assertSame('exceptional', $assessment->overall_grade);
    }

    public function test_super_admin_assessment_from_the_workspace_persists_and_returns_to_the_workspace(): void
    {
        [$advisor, $plan] = $this->plan('workspace-assessment-founder@example.test');
        $profile = $plan->entrepreneurProfile()->firstOrFail();
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        $response = $this->actingAsMfa($admin)
            ->post(route('advisor.entrepreneurs.plans.assessments.store', [
                'entrepreneurProfile' => $profile,
                'businessPlan' => $plan,
            ]));

        $assessment = PlanAssessment::query()
            ->where('business_plan_id', $plan->getKey())
            ->firstOrFail();

        $response->assertRedirect(route('advisor.entrepreneurs.show', $profile, absolute: false));
        $this->assertSame(EntrepreneurStage::ASSESSMENT, $profile->refresh()->stage);

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
        $this->assertStringContainsString('What to add/change:', $feedback);
        $this->assertStringContainsString('Where in the plan:', $feedback);
        $this->assertStringContainsString('Dear Assessment,', $reply);
        $this->assertStringContainsString('You have made real progress', $reply);
        $this->assertStringContainsString('What is missing:', $reply);
        $this->assertStringNotContainsString('Assessment completed:', $reply);
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
        return $this->response($prompt, ['score' => $this->score]);
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
}
