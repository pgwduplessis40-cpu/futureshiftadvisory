<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\AuditEvent;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\PlanPhase;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurBudgetService;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class BudgetRunwayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('security.mfa_required', false);

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_budget_runway_is_completed_and_flags_shortfall(): void
    {
        [$actor, $plan] = $this->plan();

        $budget = app(EntrepreneurBudgetService::class)->update($plan, [
            'expected_runway_months' => 8,
            'forecast_years' => 3,
            'assumptions' => [
                'revenue_growth_percent' => 12,
                'cost_inflation_percent' => 3,
                'target_gross_profit_percent' => 55,
                'target_net_profit_before_tax_percent' => 10,
                'target_net_profit_after_tax_percent' => 7,
            ],
            'launch_costs' => [
                ['label' => 'Fit out', 'amount' => 10_000, 'quantity' => 1],
            ],
            'monthly_fixed_costs' => [
                ['label' => 'Rent', 'amount' => 2_000, 'quantity' => 1],
            ],
            'revenue_forecast' => [
                ['label' => 'Subscriptions', 'amount' => 1_500, 'quantity' => 1, 'month' => 1, 'monthly_growth_percent' => 0, 'variable_cost_percent' => 10],
            ],
            'funding_sources' => [
                ['label' => 'Founder cash', 'amount' => 5_000, 'quantity' => 1],
            ],
        ], $actor);

        $this->assertSame(EntrepreneurBudget::STATUS_COMPLETE, $budget->status);
        $this->assertSame(8, $budget->expected_runway_months);
        $this->assertSame(3, $budget->forecast_years);
        $this->assertSame(-5000.0, (float) data_get($budget->computed, 'available_after_launch'));
        $this->assertContains('funding_shortfall', collect($budget->flags)->pluck('key')->all());
    }

    public function test_budget_uses_configurable_runway_tolerance_forecast_options_and_skips_noop_audit(): void
    {
        config()->set('entrepreneurs.budget.runway_mismatch_tolerance_months', 4);

        [$actor, $plan] = $this->plan();
        $payload = [
            'expected_runway_months' => 8,
            'forecast_years' => 2,
            'assumptions' => [
                'revenue_growth_percent' => -10,
                'cost_inflation_percent' => -2,
                'target_gross_profit_percent' => 55,
                'target_net_profit_before_tax_percent' => 10,
                'target_net_profit_after_tax_percent' => 7,
            ],
            'monthly_fixed_costs' => [
                ['label' => 'Rent', 'amount' => 1_000, 'quantity' => 1],
            ],
            'funding_sources' => [
                ['label' => 'Founder cash', 'amount' => 5_000, 'quantity' => 1],
            ],
        ];

        $budget = app(EntrepreneurBudgetService::class)->update($plan, $payload, $actor);
        $auditCount = DB::table('audit_events')
            ->where('action', 'entrepreneur.budget_updated')
            ->count();

        $this->assertSame(2, $budget->forecast_years);
        $this->assertEquals(-10.0, data_get($budget->computed, 'assumptions.revenue_growth_percent'));
        $this->assertEquals(-2.0, data_get($budget->computed, 'assumptions.cost_inflation_percent'));
        $this->assertNotContains('runway_mismatch', collect($budget->flags)->pluck('key')->all());

        app(EntrepreneurBudgetService::class)->update($plan, $payload, $actor);

        $this->assertSame(
            $auditCount,
            DB::table('audit_events')
                ->where('action', 'entrepreneur.budget_updated')
                ->count(),
        );
    }

    public function test_budget_rejects_period_counts_as_fixed_cost_quantities(): void
    {
        [$actor, $plan] = $this->plan();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Qty is the number of parallel subscriptions');

        app(EntrepreneurBudgetService::class)->update($plan, [
            'monthly_fixed_costs' => [
                ['label' => 'Owner pay', 'amount' => 850, 'quantity' => 52, 'cadence' => 'weekly'],
            ],
        ], $actor);
    }

    public function test_budget_revision_prevents_stale_overwrites_and_audits_raw_input_rows(): void
    {
        [$actor, $plan] = $this->plan();
        $service = app(EntrepreneurBudgetService::class);

        $created = $service->update($plan, [
            'monthly_fixed_costs' => [
                ['label' => 'Owner pay', 'amount' => 850, 'quantity' => 1, 'cadence' => 'weekly'],
            ],
        ], $actor);
        $updated = $service->update($plan, [
            'revision' => $created->revision,
            'monthly_fixed_costs' => [
                ['label' => 'Owner pay', 'amount' => 875, 'quantity' => 1, 'cadence' => 'weekly'],
            ],
        ], $actor);

        $audit = AuditEvent::query()
            ->where('action', 'entrepreneur.budget_updated')
            ->get()
            ->first(fn (AuditEvent $event): bool => data_get($event->after, 'revision') === 2);

        $this->assertSame(1, $created->revision);
        $this->assertSame(2, $updated->revision);
        $this->assertInstanceOf(AuditEvent::class, $audit);
        $this->assertSame(1, data_get($audit->before, 'revision'));
        $this->assertEquals(850.0, data_get($audit->before, 'monthly_fixed_costs.0.amount'));
        $this->assertSame(2, data_get($audit->after, 'revision'));
        $this->assertEquals(875.0, data_get($audit->after, 'monthly_fixed_costs.0.amount'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('changed in another browser session');

        $service->update($plan, [
            'revision' => 1,
            'monthly_fixed_costs' => [
                ['label' => 'Owner pay', 'amount' => 900, 'quantity' => 1, 'cadence' => 'weekly'],
            ],
        ], $actor);
    }

    public function test_locked_budget_pdf_redirects_with_a_clear_explanation(): void
    {
        [$actor] = $this->plan();

        $this->actingAs($actor)
            ->get(route('portal.entrepreneur.plan.budget-pack.pdf'))
            ->assertRedirect(route('portal.entrepreneur.plan.show'))
            ->assertSessionHas('status', 'entrepreneur-budget-locked')
            ->assertSessionHas('entrepreneur_plan_error', 'Complete Foundation: Business type, location, and operating model, plus Financial: Financial assumptions before viewing the budget PDF.');
    }

    public function test_missing_financial_assumptions_keep_budget_partial_and_flagged(): void
    {
        [$actor, $plan] = $this->plan();

        $budget = app(EntrepreneurBudgetService::class)->update($plan, [
            'expected_runway_months' => 8,
            'forecast_years' => 1,
            'assumptions' => [
                'revenue_growth_percent' => 5,
            ],
            'launch_costs' => [
                ['label' => 'Website', 'amount' => 1_000, 'confidence' => 'known'],
            ],
            'monthly_fixed_costs' => [
                ['label' => 'Software', 'amount' => 300, 'confidence' => 'known'],
            ],
            'revenue_forecast' => [
                ['label' => 'Subscriptions', 'amount' => 2_000, 'month' => 1, 'confidence' => 'known'],
            ],
            'funding_sources' => [
                ['label' => 'Founder cash', 'amount' => 15_000, 'confidence' => 'known'],
            ],
        ], $actor);

        $this->assertSame(EntrepreneurBudget::STATUS_PARTIAL, $budget->status);
        $this->assertContains(
            'cost_inflation_percent',
            data_get($budget->computed, 'missing_assumptions', []),
        );
        $this->assertContains('missing_financial_assumptions', collect($budget->flags)->pluck('key')->all());
    }

    public function test_large_fixed_costs_and_high_monthly_growth_are_flagged_for_review(): void
    {
        config()->set('entrepreneurs.budget.monthly_fixed_cost_review_threshold', 50_000);
        config()->set('entrepreneurs.budget.monthly_growth_review_threshold_percent', 15);
        [$actor, $plan] = $this->plan();

        $budget = app(EntrepreneurBudgetService::class)->update($plan, [
            'expected_runway_months' => 6,
            'forecast_years' => 1,
            'assumptions' => [
                'revenue_growth_percent' => 10,
                'cost_inflation_percent' => 3,
                'target_gross_profit_percent' => 55,
                'target_net_profit_before_tax_percent' => 10,
                'target_net_profit_after_tax_percent' => 7,
            ],
            'launch_costs' => [
                ['label' => 'Fit out', 'amount' => 20_000, 'confidence' => 'known'],
            ],
            'monthly_fixed_costs' => [
                ['label' => 'Premises and staffing', 'amount' => 51_573, 'confidence' => 'estimate'],
            ],
            'revenue_forecast' => [
                ['label' => 'Subscriptions', 'amount' => 10_000, 'month' => 1, 'monthly_growth_percent' => 25, 'confidence' => 'estimate'],
            ],
            'funding_sources' => [
                ['label' => 'Founder cash', 'amount' => 500_000, 'confidence' => 'known'],
            ],
        ], $actor);

        $flags = collect($budget->flags)->pluck('key')->all();

        $this->assertContains('large_monthly_fixed_cost_base', $flags);
        $this->assertContains('monthly_revenue_growth_needs_review', $flags);
    }

    public function test_budget_cannot_be_saved_before_business_setup_requirement_is_complete(): void
    {
        [$actor, $plan] = $this->plan();
        $phase = $plan->phases()->firstOrFail();

        $this->actingAs($actor)
            ->post(route('portal.entrepreneur.plan.budget.update'), [
                'expected_runway_months' => 6,
                'launch_costs' => [
                    ['label' => 'Website', 'amount' => 500, 'quantity' => 1],
                ],
            ])
            ->assertRedirect(route('portal.entrepreneur.plan.show'))
            ->assertSessionHas('status', 'entrepreneur-budget-locked');

        $this->assertDatabaseMissing('entrepreneur_budgets', [
            'business_plan_id' => $plan->id,
        ]);

        PlanSection::query()->create([
            'business_plan_id' => $plan->id,
            'plan_phase_id' => $phase->id,
            'key' => 'founder-foundation-business-type-location',
            'title' => 'Business type, location, and operating model',
            'body' => str_repeat('A practical service business operating locally. ', 3),
            'source_type' => 'founder',
            'completeness_status' => PlanSection::STATUS_COMPLETE,
            'metadata' => ['requirement_key' => 'business-type-location'],
        ]);

        $this->actingAs($actor)
            ->post(route('portal.entrepreneur.plan.budget.update'), [
                'expected_runway_months' => 6,
                'launch_costs' => [
                    ['label' => 'Website', 'amount' => 500, 'quantity' => 1],
                ],
            ])
            ->assertRedirect(route('portal.entrepreneur.plan.show'))
            ->assertSessionHas('status', 'entrepreneur-budget-locked');

        PlanSection::query()->create([
            'business_plan_id' => $plan->id,
            'plan_phase_id' => $phase->id,
            'key' => 'founder-financial-financial-assumptions',
            'title' => 'Financial assumptions',
            'body' => str_repeat('Revenue grows by ten percent, cost inflation is three percent, and target margins are set for gross and net profit. ', 3),
            'source_type' => 'founder',
            'completeness_status' => PlanSection::STATUS_COMPLETE,
            'metadata' => ['requirement_key' => 'financial-assumptions'],
        ]);

        $this->actingAs($actor)
            ->post(route('portal.entrepreneur.plan.budget.update'), [
                'expected_runway_months' => 6,
                'forecast_years' => 1,
                'assumptions' => [
                    'revenue_growth_percent' => -10,
                    'cost_inflation_percent' => -2,
                    'target_gross_profit_percent' => 55,
                    'target_net_profit_before_tax_percent' => 10,
                    'target_net_profit_after_tax_percent' => 7,
                ],
                'launch_costs' => [
                    ['label' => 'Website', 'amount' => 500, 'quantity' => 1, 'month' => 2],
                ],
                'revenue_forecast' => [
                    ['label' => 'Sales', 'amount' => 1_000, 'quantity' => 1, 'month' => 1, 'monthly_growth_percent' => -5],
                ],
                'funding_sources' => [
                    ['label' => 'Founder cash', 'amount' => 1_000, 'quantity' => 1],
                ],
            ])
            ->assertRedirect(route('portal.entrepreneur.plan.show'))
            ->assertSessionHas('status', 'entrepreneur-budget-saved');

        $budget = EntrepreneurBudget::query()
            ->where('business_plan_id', $plan->id)
            ->firstOrFail();

        $this->assertSame(1, $budget->forecast_years);
        $this->assertEquals(-10.0, data_get($budget->computed, 'assumptions.revenue_growth_percent'));
        $this->assertEquals(-2.0, data_get($budget->computed, 'assumptions.cost_inflation_percent'));
        $this->assertEquals(500.0, data_get($budget->computed, 'monthly_detail.1.launch_costs'));
    }

    /**
     * @return array{0:User, 1:BusinessPlan}
     */
    private function plan(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Budget Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::BUILDING_PHASE_1,
            'concept_summary' => 'Budget concept.',
        ]);
        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'title' => 'Budget test plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_BUILDING,
            'current_phase' => 1,
            'created_by_user_id' => $entrepreneur->id,
        ]);

        PlanPhase::query()->create([
            'business_plan_id' => $plan->id,
            'key' => 'fixture',
            'title' => 'Fixture phase',
            'position' => 1,
            'status' => PlanPhase::STATUS_PENDING,
        ]);

        return [$entrepreneur, $plan];
    }
}
