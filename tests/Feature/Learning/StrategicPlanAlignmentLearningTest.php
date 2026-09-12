<?php

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\LearningUpdate;
use App\Models\StrategicBudget;
use App\Models\StrategicPlan;
use App\Models\User;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\Learning\LayerCadenceRunner;
use App\Services\Learning\StrategicPlanAlignmentLearning;
use App\Services\StrategicPlans\StrategicPlanEvidenceReview;
use App\Services\StrategicPlans\StrategicPlanService;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StrategicPlanAlignmentLearningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_unresolved_business_plan_and_budget_checks_create_one_governed_learning_candidate(): void
    {
        $budget = $this->budget($this->client(), [
            'business_plan_sections' => [[
                'key' => 'market_customers',
                'title' => 'Market and customers',
                'financial_drivers' => [[
                    'key' => 'driver_enterprise_sales',
                    'category' => 'revenue_forecast',
                    'label' => 'Enterprise sales',
                    'amount' => 10_000,
                    'quantity' => 2,
                    'cadence' => 'monthly',
                    'cadence_confirmed' => true,
                    'growth_percent' => 5,
                    'growth_cadence' => 'monthly',
                    'growth_cadence_confirmed' => true,
                    'monthly_capacity_units' => 2,
                    'capacity_confirmed' => true,
                ]],
            ]],
            'revenue_forecast' => [],
        ]);

        $learning = app(StrategicPlanAlignmentLearning::class);
        $first = $learning->syncBudgetPlanCoherence($budget);
        $second = $learning->syncBudgetPlanCoherence($budget->refresh());

        $this->assertInstanceOf(LearningUpdate::class, $first);
        $this->assertSame($first->getKey(), $second?->getKey());
        $this->assertSame(1, LearningUpdate::query()->count());
        $this->assertSame(LayerCadenceRegistry::LAYER_STRATEGIC_PLAN_ALIGNMENT, $first->layer_id);
        $this->assertSame('strategic_budget_plan_budget_alignment', data_get($first->source, 'type'));
        $this->assertSame('review_business_plan_budget_alignment', data_get($first->proposed_change, 'action'));
        $this->assertFalse((bool) data_get($first->proposed_change, 'automatic_application'));
        $this->assertContains('budget_support', collect(data_get($first->evidence, 'findings', []))->pluck('category')->all());
        $this->assertContains('plan_correlation', collect(data_get($first->evidence, 'findings', []))->pluck('category')->all());
    }

    public function test_strategic_plan_with_untraced_client_and_business_plan_inputs_is_queued_for_review(): void
    {
        $client = $this->client();
        $budget = $this->budget($client, [
            'client_goals' => [[
                'title' => 'Increase recurring service revenue',
                'measure' => 'Reach 40 active retainers',
            ]],
            'advisor_goals' => [[
                'title' => 'Publish monthly cash flow review',
                'measure' => 'Review cash position every month',
            ]],
            'business_plan_sections' => [[
                'key' => 'action_priorities',
                'title' => 'Action priorities',
                'answer' => 'Reduce customer onboarding delays and clarify the delivery workflow.',
            ]],
        ]);
        $plan = $this->plan($client, $budget, [
            $this->section('outcomes', 'Improve brand awareness.'),
            $this->section('priorities', 'Refresh the website homepage.'),
            $this->section('milestones', 'Review progress every month.'),
            $this->section('budget', 'Use the current budget.'),
            $this->section('governance', 'Advisor and client meet monthly.'),
        ]);

        $update = app(StrategicPlanAlignmentLearning::class)->syncStrategicPlan($plan);

        $this->assertInstanceOf(LearningUpdate::class, $update);
        $this->assertSame('strategic_plan_evidence_alignment', data_get($update->source, 'type'));
        $this->assertSame('review_strategic_plan_evidence_alignment', data_get($update->proposed_change, 'action'));
        $this->assertSame($plan->getKey(), data_get($update->impact_scope, 'strategic_plan_id'));
        $this->assertTrue((bool) data_get($update->evidence, 'client_narrative_excluded'));

        $categories = collect(data_get($update->evidence, 'findings', []))->pluck('category')->all();
        $this->assertContains('client_goal', $categories);
        $this->assertContains('advisor_goal', $categories);
        $this->assertContains('business_plan_priority', $categories);
    }

    public function test_strategic_plan_review_handles_a_budget_without_goal_arrays(): void
    {
        $client = $this->client();
        $budget = $this->budget($client, [
            'client_goals' => null,
            'advisor_goals' => null,
        ]);
        $plan = $this->plan($client, $budget, [
            $this->section('outcomes', 'Improve brand awareness.'),
            $this->section('priorities', 'Refresh the website homepage.'),
            $this->section('milestones', 'Review progress every month.'),
            $this->section('budget', 'Use the current budget.'),
            $this->section('governance', 'Advisor and client meet monthly.'),
        ]);

        $update = app(StrategicPlanAlignmentLearning::class)->syncStrategicPlan($plan);

        $this->assertInstanceOf(LearningUpdate::class, $update);
        $this->assertSame(0, data_get($update->evidence, 'client_goal_count'));
        $this->assertSame(0, data_get($update->evidence, 'advisor_goal_count'));
    }

    public function test_strategic_plan_updates_refresh_the_same_candidate_instead_of_adding_queue_noise(): void
    {
        $client = $this->client();
        $budget = $this->budget($client, [
            'client_goals' => [[
                'title' => 'Increase recurring service revenue',
                'measure' => 'Reach 40 active retainers',
            ]],
        ]);
        $plan = $this->plan($client, $budget, [
            $this->section('outcomes', 'Improve brand awareness.'),
            $this->section('priorities', 'Refresh the website homepage.'),
            $this->section('milestones', 'Review progress every month.'),
            $this->section('budget', 'Use the current budget.'),
            $this->section('governance', 'Advisor and client meet monthly.'),
        ]);
        $advisor = User::factory()->create();

        app(StrategicPlanService::class)->update($plan, [
            'summary' => 'Updated strategic plan.',
            'sections' => $plan->sections,
            'milestones' => [],
        ], $advisor);
        app(StrategicPlanService::class)->update($plan->refresh(), [
            'summary' => 'Updated strategic plan again.',
            'sections' => $plan->sections,
            'milestones' => [],
        ], $advisor);

        $this->assertSame(1, LearningUpdate::query()
            ->where('source->type', 'strategic_plan_evidence_alignment')
            ->count());
    }

    public function test_explicit_evidence_links_are_snapshotted_and_changes_to_their_sources_request_review(): void
    {
        $client = $this->client();
        $budget = $this->budget($client, [
            'client_goals' => [[
                'title' => 'Increase recurring service revenue',
                'measure' => 'Reach 40 active retainers',
            ]],
            'business_plan_sections' => [[
                'key' => 'action_priorities',
                'title' => 'Action priorities',
                'answer' => 'Reduce customer onboarding delays.',
            ]],
        ]);
        $plan = $this->plan($client, $budget, [
            $this->section('outcomes', 'Increase recurring service revenue to 40 active retainers.'),
            $this->section('priorities', 'Reduce customer onboarding delays.'),
            $this->section('milestones', 'Review progress every month.'),
            $this->section('budget', 'Use the approved Business Plan and Budget.'),
            $this->section('governance', 'Advisor and client meet monthly.'),
        ]);
        $bindings = [
            $this->binding('strategic_budget:'.$budget->getKey(), 'section:budget'),
            $this->binding('client_goal:0', 'section:outcomes'),
            $this->binding('business_plan_section:action_priorities', 'section:priorities'),
        ];
        $review = app(StrategicPlanEvidenceReview::class);
        $plan->forceFill([
            'evidence_bindings' => $review->normaliseBindings($plan, $bindings, true),
            'source_snapshots' => $review->captureSourceSnapshots($plan),
        ])->save();

        $this->assertNull(app(StrategicPlanAlignmentLearning::class)->syncStrategicPlan($plan->refresh()));

        $budget->forceFill([
            'client_goals' => [[
                'title' => 'Increase recurring service revenue',
                'measure' => 'Reach 60 active retainers',
            ]],
        ])->save();

        $update = app(StrategicPlanAlignmentLearning::class)->syncStrategicPlan($plan->refresh());

        $this->assertInstanceOf(LearningUpdate::class, $update);
        $categories = collect(data_get($update->evidence, 'findings', []))->pluck('category')->all();
        $this->assertContains('source_snapshot_stale', $categories);
        $this->assertContains('source_binding_stale', $categories);
    }

    public function test_material_outcome_variance_is_queued_without_automatic_plan_changes(): void
    {
        $client = $this->client();
        $budget = $this->budget($client);
        $plan = $this->plan($client, $budget, [
            $this->section('outcomes', 'Increase active retainers.'),
            $this->section('priorities', 'Improve delivery process.'),
            $this->section('milestones', 'Measure active retainers.'),
            $this->section('budget', 'Use the approved budget.'),
            $this->section('governance', 'Review delivery monthly.'),
        ]);
        $plan->forceFill(['status' => StrategicPlan::STATUS_DEPLOYED])->save();
        $plan->milestones()->create([
            'client_id' => $client->getKey(),
            'title' => 'Increase active retainers',
            'owner' => 'joint',
            'due_offset_days' => 30,
            'status' => 'completed',
            'progress_percent' => 100,
            'metric_label' => 'Active retainers',
            'measurement_unit' => 'retainers',
            'target_direction' => 'increase',
            'baseline_value' => 20,
            'target_value' => 40,
            'actual_value' => 26,
            'measurement_updated_at' => now(),
        ]);

        $update = app(StrategicPlanAlignmentLearning::class)->syncStrategicPlan($plan->refresh());

        $this->assertInstanceOf(LearningUpdate::class, $update);
        $this->assertContains(
            'outcome_variance',
            collect(data_get($update->evidence, 'findings', []))->pluck('category')->all(),
        );
        $this->assertSame('high', $update->magnitude);
        $this->assertFalse((bool) data_get($update->proposed_change, 'automatic_application'));
    }

    public function test_weekly_learning_cadence_runs_the_strategic_plan_alignment_review(): void
    {
        $client = $this->client();
        $budget = $this->budget($client, [
            'client_goals' => [[
                'title' => 'Increase recurring service revenue',
                'measure' => 'Reach 40 active retainers',
            ]],
        ]);
        $this->plan($client, $budget, [
            $this->section('outcomes', 'Improve brand awareness.'),
            $this->section('priorities', 'Refresh the website homepage.'),
            $this->section('milestones', 'Review progress every month.'),
            $this->section('budget', 'Use the current budget.'),
            $this->section('governance', 'Advisor and client meet monthly.'),
        ]);

        $runs = app(LayerCadenceRunner::class)->recordDueRuns(now(), [
            LayerCadenceRegistry::LAYER_STRATEGIC_PLAN_ALIGNMENT,
        ]);

        $this->assertSame(1, $runs->count());
        $this->assertSame(1, $runs->first()?->candidates_created);
        $this->assertSame(1, LearningUpdate::query()
            ->where('source->type', 'strategic_plan_evidence_alignment')
            ->count());
    }

    private function client(): Client
    {
        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'legal_name' => 'Planning Learning Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function budget(Client $client, array $attributes = []): StrategicBudget
    {
        return StrategicBudget::query()->create([
            'client_id' => $client->getKey(),
            'pathway' => StrategicBudget::PATHWAY_ADVISORY,
            'label' => 'Business Plan & Budget',
            'status' => StrategicBudget::STATUS_CLIENT_WORKING_DRAFT,
            'horizon_months' => 12,
            'client_goals' => [],
            'advisor_goals' => [],
            'business_plan_sections' => [],
            'assumptions' => [],
            'implementation_costs' => [],
            'monthly_fixed_costs' => [],
            'future_costs' => [],
            'revenue_forecast' => [],
            'funding_sources' => [],
            'funding_scenarios' => [],
            'computed' => [],
            'flags' => [],
            'confidence' => [],
            ...$attributes,
        ]);
    }

    /** @param list<array{key:string,title:string,body:string}> $sections */
    private function plan(Client $client, StrategicBudget $budget, array $sections): StrategicPlan
    {
        return StrategicPlan::query()->create([
            'client_id' => $client->getKey(),
            'strategic_budget_id' => $budget->getKey(),
            'title' => 'Strategic Plan',
            'status' => StrategicPlan::STATUS_DRAFT,
            'summary' => 'Planning fixture.',
            'sections' => $sections,
        ]);
    }

    /** @return array{key:string,title:string,body:string} */
    private function section(string $key, string $body): array
    {
        return [
            'key' => $key,
            'title' => str($key)->replace('_', ' ')->title()->toString(),
            'body' => $body,
        ];
    }

    /** @return array{source_key:string,target_key:string,disposition:string,rationale:string} */
    private function binding(string $sourceKey, string $targetKey): array
    {
        return [
            'source_key' => $sourceKey,
            'target_key' => $targetKey,
            'disposition' => 'supports',
            'rationale' => 'Reviewed against the current strategic delivery plan.',
        ];
    }
}
