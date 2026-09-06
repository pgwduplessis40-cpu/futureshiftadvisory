<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\LearningRecommendation;
use App\Models\LearningUpdate;
use App\Models\User;
use App\Services\Learning\ApprovalFlow;
use App\Services\Learning\LearningRecommendationWorkflow;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
