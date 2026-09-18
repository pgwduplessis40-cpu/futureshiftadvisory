<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EntrepreneurStage;
use App\Http\Controllers\Advisor\AdvisorEntrepreneurWorkspacePayload;
use App\Jobs\RunEntrepreneurPlanAssessment;
use App\Models\AuditEvent;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class EntrepreneurBudgetCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_advisor_can_correct_a_duplicate_weekly_cadence_quantity_and_queue_a_reassessment(): void
    {
        Queue::fake();
        [$advisor, $profile, $plan, $budget] = $this->budgetWithDuplicatedWeeklyQuantity();

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.entrepreneurs.plans.budget.fixed-cost-cadence.repair', [$profile, $plan]), [
                'row_index' => 0,
            ])
            ->assertRedirect(route('advisor.entrepreneurs.show', $profile));

        $budget->refresh();
        $plan->refresh();
        $audit = AuditEvent::query()
            ->where('action', 'entrepreneur.budget_fixed_cost_cadence_repaired')
            ->first();

        $this->assertSame(875.0, (float) $budget->monthly_fixed_costs[0]['amount']);
        $this->assertSame(1.0, (float) $budget->monthly_fixed_costs[0]['quantity']);
        $this->assertSame(2, $budget->revision);
        $this->assertSame(BusinessPlan::STATUS_ASSESSING, $plan->status);
        $this->assertSame('queued', $plan->assessment_run_status);
        $this->assertInstanceOf(AuditEvent::class, $audit);
        $this->assertSame(52.0, (float) data_get($audit->after, 'before.fixed_cost.quantity'));
        $this->assertSame(1.0, (float) data_get($audit->after, 'after.fixed_cost.quantity'));
        Queue::assertPushed(RunEntrepreneurPlanAssessment::class);
    }

    public function test_advisor_budget_view_exposes_the_rate_units_cadence_and_monthly_equivalent(): void
    {
        [$advisor, $profile, $plan] = $this->budgetWithDuplicatedWeeklyQuantity();

        $payload = app(AdvisorEntrepreneurWorkspacePayload::class)->show($advisor, $profile->fresh());

        $this->assertSame('Owner compensation', data_get($payload, 'entrepreneur.latest_plan.budget.fixed_cost_trace.0.label'));
        $this->assertSame(875.0, data_get($payload, 'entrepreneur.latest_plan.budget.fixed_cost_trace.0.rate'));
        $this->assertSame(52.0, data_get($payload, 'entrepreneur.latest_plan.budget.fixed_cost_trace.0.quantity'));
        $this->assertSame('weekly', data_get($payload, 'entrepreneur.latest_plan.budget.fixed_cost_trace.0.cadence'));
        $this->assertSame(197166.67, data_get($payload, 'entrepreneur.latest_plan.budget.fixed_cost_trace.0.monthly_equivalent'));
        $this->assertTrue(data_get($payload, 'entrepreneur.latest_plan.budget.fixed_cost_trace.0.duplicate_cadence_quantity'));
        $this->assertSame(route('advisor.entrepreneurs.plans.budget.fixed-cost-cadence.repair', [
            $profile,
            $plan,
        ], absolute: false), data_get($payload, 'entrepreneur.latest_plan.budget.fixed_cost_cadence_repair_url'));
    }

    /** @return array{User, EntrepreneurProfile, BusinessPlan, EntrepreneurBudget} */
    private function budgetWithDuplicatedWeeklyQuantity(): array
    {
        $this->seed(RoleSeeder::class);
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $founder = User::factory()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $founder->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $founder->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => 'Tania Budget Test',
            'email' => $founder->email,
            'stage' => EntrepreneurStage::ASSESSMENT,
        ]);
        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'title' => 'Budget correction test plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_SUBMITTED,
            'created_by_user_id' => $founder->getKey(),
        ]);
        $budget = EntrepreneurBudget::query()->create([
            'business_plan_id' => $plan->getKey(),
            'revision' => 1,
            'status' => EntrepreneurBudget::STATUS_COMPLETE,
            'forecast_years' => 1,
            'assumptions' => ['opening_cash_balance' => 0],
            'launch_costs' => [],
            'monthly_fixed_costs' => [[
                'label' => 'Owner compensation',
                'amount' => 875,
                'quantity' => 52,
                'cadence' => 'weekly',
                'cadence_confirmed' => true,
                'confidence' => 'known',
            ]],
            'future_costs' => [],
            'revenue_forecast' => [],
            'funding_sources' => [],
            'funding_scenarios' => [],
            'computed' => [],
            'flags' => [],
        ]);

        return [$advisor, $profile, $plan, $budget];
    }
}
