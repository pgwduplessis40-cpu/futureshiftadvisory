<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\LearningRecommendation;
use App\Models\LearningUpdate;
use App\Models\User;
use App\Services\Learning\ApprovalFlow;
use App\Services\Learning\LearningRecommendationWorkflow;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class LearningRecommendationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_approved_recommendation_requires_delivery_and_verification_before_it_is_addressed(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->superAdmin()->create();
        $update = LearningUpdate::query()->create([
            'layer_id' => 3,
            'source' => ['type' => 'bias_detector', 'prompt' => 'entrepreneur.plan_score_criterion'],
            'summary' => 'Praise-heavy language was detected in a score criterion.',
            'proposed_change' => ['action' => 'review_prompt_or_output_policy'],
            'impact_scope' => ['module' => 'entrepreneur_plan_score_criterion'],
            'clients_affected' => 4,
            'magnitude' => 'medium',
            'confidence' => 0.5,
            'evidence' => ['signal' => 'praise language: exceptional'],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
        $workflow = app(LearningRecommendationWorkflow::class);

        $recommendation = $workflow->draft($update, $admin, [
            'title' => 'Calibrate praise language in score criteria',
            'failure_shortfall' => 'The criterion can overstate evidence through praise-heavy language.',
            'impact' => 'Advisor decisions may be influenced by wording that is more favourable than the evidence supports.',
            'impact_area' => 'Entrepreneur plan scoring',
            'recommendation' => 'Replace praise-led wording with evidence-calibrated criteria and add a regression assertion.',
            'recommendation_impact' => 'Scores and advisor advice become more evidence-aligned without changing valid plan content.',
            'acceptance_criteria' => ['Criterion uses calibrated wording.', 'Bias detection regression test passes.'],
            'regression_journeys' => ['Entrepreneur plan assessment', 'Advisor scoring review'],
        ]);

        $this->assertSame(LearningRecommendation::STATUS_DRAFT, $recommendation->status);
        $this->assertSame(LearningUpdate::STATUS_STAGED, $update->refresh()->status);

        $workflow->approve($recommendation, $admin);
        $this->assertSame(LearningRecommendation::STATUS_APPROVED, $recommendation->refresh()->status);
        $this->assertSame(LearningUpdate::STATUS_APPROVED, $update->refresh()->status);
        $this->assertCount(0, app(ApprovalFlow::class)->implementDue(now(), $admin));

        $workflow->updateDelivery($recommendation, $admin, [
            'status' => LearningRecommendation::STATUS_IN_DEVELOPMENT,
            'development_reference' => 'PR #123',
        ]);
        $workflow->updateDelivery($recommendation->refresh(), $admin, [
            'status' => LearningRecommendation::STATUS_RELEASED,
            'release_reference' => 'deploy-production 1.0.165',
        ]);
        $this->assertStringContainsString('Calibrate praise language', $workflow->developerBrief());
        $workflow->updateDelivery($recommendation->refresh(), $admin, [
            'status' => LearningRecommendation::STATUS_VERIFIED,
            'verification_notes' => 'Assessment and advisor review regression journeys passed after deployment.',
        ]);

        $recommendation->refresh();
        $this->assertSame(LearningRecommendation::STATUS_VERIFIED, $recommendation->status);
        $this->assertNotNull($recommendation->released_at);
        $this->assertNotNull($recommendation->verified_at);
        $this->assertDatabaseHas('audit_events', ['action' => 'learning_recommendation.approved']);
        $this->assertDatabaseHas('audit_events', ['action' => 'learning_recommendation.delivery_updated']);
    }

    public function test_learning_queue_separates_draft_recommendations_from_learning_creation_work(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->admin();
        $recommendation = $this->draftRecommendation($admin, 'Review the financial prompt evidence.');

        $this->actingAsMfa($admin)
            ->get(route('admin.learning-updates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/learning/Index')
                ->has('cards', 0)
                ->has('recommendations', 1)
                ->where('recommendations.0.id', $recommendation->id)
                ->where('recommendations.0.status', LearningRecommendation::STATUS_DRAFT)
                ->where('recommendation_defaults', [])
                ->where('recommendation_bulk_approve_url', route('admin.learning-recommendations.approve-selected', absolute: false)),
            );
    }

    public function test_admin_can_approve_multiple_draft_recommendations_for_development(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->admin();
        $first = $this->draftRecommendation($admin, 'Review the financial prompt evidence.');
        $second = $this->draftRecommendation($admin, 'Review the budget evidence prompt.');

        $this->actingAsMfa($admin)
            ->post(route('admin.learning-recommendations.approve-selected'), [
                'recommendation_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect(route('admin.learning-updates.index', absolute: false));

        $this->assertSame(LearningRecommendation::STATUS_APPROVED, $first->refresh()->status);
        $this->assertSame(LearningRecommendation::STATUS_APPROVED, $second->refresh()->status);
        $this->assertSame(LearningUpdate::STATUS_APPROVED, $first->learningUpdate->refresh()->status);
        $this->assertSame(LearningUpdate::STATUS_APPROVED, $second->learningUpdate->refresh()->status);
        $this->assertDatabaseCount('learning_update_decisions', 2);
        $this->assertSame(2, AuditEvent::query()
            ->where('action', 'learning_recommendation.approved')
            ->count());
    }

    private function admin(): User
    {
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        return $admin;
    }

    private function draftRecommendation(User $admin, string $summary): LearningRecommendation
    {
        $update = LearningUpdate::query()->create([
            'layer_id' => 3,
            'source' => ['type' => 'analysis_feedback', 'prompt' => 'analysis.financial'],
            'summary' => $summary,
            'proposed_change' => ['action' => 'review_prompt_or_output_policy'],
            'impact_scope' => ['module' => 'financial'],
            'clients_affected' => 4,
            'magnitude' => 'medium',
            'confidence' => 0.5,
            'evidence' => ['signal' => 'supporting evidence requires review'],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        return app(LearningRecommendationWorkflow::class)->draft($update, $admin, [
            'title' => $summary,
            'failure_shortfall' => 'The governed learning needs a traceable development decision.',
            'impact' => 'Unreviewed prompt evidence could weaken advice quality.',
            'impact_area' => 'Financial analysis',
            'recommendation' => 'Review the evidence and implement the governed prompt change.',
            'recommendation_impact' => 'The prompt remains evidence-based and reviewable.',
            'acceptance_criteria' => ['Evidence remains visible to reviewers.'],
            'regression_journeys' => ['Financial analysis review'],
        ]);
    }
}
