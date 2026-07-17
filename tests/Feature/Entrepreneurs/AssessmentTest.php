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
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Entrepreneurs\PlanBuilder;
use App\Support\RequestContext;
use Database\Seeders\FoundingRatingFrameworkValuesSeeder;
use Database\Seeders\RatingFrameworkSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
