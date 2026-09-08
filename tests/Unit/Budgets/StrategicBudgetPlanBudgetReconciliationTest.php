<?php

declare(strict_types=1);

namespace Tests\Unit\Budgets;

use App\Models\StrategicBudget;
use App\Services\Budgets\StrategicBudgetPlanBudgetReconciliation;
use Tests\TestCase;

final class StrategicBudgetPlanBudgetReconciliationTest extends TestCase
{
    public function test_it_blocks_a_budget_that_contradicts_confirmed_plan_assumptions(): void
    {
        $result = (new StrategicBudgetPlanBudgetReconciliation)->evaluate(new StrategicBudget([
            'assumptions' => ['opening_cash_balance' => 0],
            'computed' => ['runway_months' => 0, 'runway_open_ended' => false],
            'business_plan_sections' => [[
                'key' => 'goals',
                'title' => 'Goals',
                'financial_drivers' => [
                    $this->driver('opening_cash', 'Opening cash', 7_830),
                    $this->driver('runway_target', 'Minimum runway', 12),
                ],
            ], [
                'key' => 'operations',
                'title' => 'Operations',
                'financial_drivers' => [
                    $this->driver('owner_draw', 'Owner draw', 550, cadence: 'weekly', cadenceConfirmed: true),
                ],
            ], [
                'key' => 'market_customers',
                'title' => 'Market and customers',
                'financial_drivers' => [
                    $this->driver(
                        'intensive_days',
                        'Intensive delivery days',
                        1_500,
                        growthPercent: 25,
                        growthCadence: 'annual',
                        capacity: 8,
                    ),
                ],
            ]],
            'monthly_fixed_costs' => [[
                'label' => 'Owner draw',
                'amount' => 45_500,
                'cadence' => 'monthly',
                'cadence_confirmed' => true,
                'plan_financial_driver_key' => 'owner_draw',
            ]],
            'revenue_forecast' => [[
                'label' => 'Intensive delivery days',
                'amount' => 1_500,
                'growth_percent' => 25,
                'growth_cadence' => 'monthly',
                'growth_cadence_confirmed' => true,
                'monthly_capacity_units' => 95,
                'capacity_confirmed' => true,
                'plan_financial_driver_key' => 'intensive_days',
            ]],
        ]));

        $messages = collect($result['findings'])->pluck('message')->join(' ');

        $this->assertSame('review', $result['status']);
        $this->assertFalse($result['approval_available']);
        $this->assertStringContainsString('opening cash of $7,830', $messages);
        $this->assertStringContainsString('targets 12 months of runway', $messages);
        $this->assertStringContainsString('after cadence normalisation', $messages);
        $this->assertStringContainsString('above the plan’s confirmed capacity of 8.00', $messages);
        $this->assertStringContainsString('growth as 25.00% annual', $messages);
    }

    public function test_it_accepts_aligned_confirmed_plan_assumptions(): void
    {
        $result = (new StrategicBudgetPlanBudgetReconciliation)->evaluate(new StrategicBudget([
            'assumptions' => ['opening_cash_balance' => 7_830],
            'computed' => ['runway_months' => 12, 'runway_open_ended' => false],
            'business_plan_sections' => [[
                'key' => 'goals',
                'title' => 'Goals',
                'financial_drivers' => [
                    $this->driver('opening_cash', 'Opening cash', 7_830),
                    $this->driver('runway_target', 'Minimum runway', 12),
                ],
            ], [
                'key' => 'operations',
                'title' => 'Operations',
                'financial_drivers' => [
                    $this->driver('owner_draw', 'Owner draw', 550, cadence: 'weekly', cadenceConfirmed: true),
                ],
            ], [
                'key' => 'market_customers',
                'title' => 'Market and customers',
                'financial_drivers' => [
                    $this->driver(
                        'intensive_days',
                        'Intensive delivery days',
                        1_500,
                        growthPercent: 25,
                        growthCadence: 'annual',
                        capacity: 8,
                    ),
                ],
            ]],
            'monthly_fixed_costs' => [[
                'label' => 'Owner draw',
                'amount' => 550,
                'cadence' => 'weekly',
                'cadence_confirmed' => true,
                'plan_financial_driver_key' => 'owner_draw',
            ]],
            'revenue_forecast' => [[
                'label' => 'Intensive delivery days',
                'amount' => 1_500,
                'growth_percent' => 25,
                'growth_cadence' => 'annual',
                'growth_cadence_confirmed' => true,
                'monthly_capacity_units' => 8,
                'capacity_confirmed' => true,
                'plan_financial_driver_key' => 'intensive_days',
            ]],
        ]));

        $this->assertSame('met', $result['status']);
        $this->assertTrue($result['approval_available']);
        $this->assertSame(100, $result['score']);
        $this->assertSame([], $result['findings']);
    }

    public function test_it_requires_confirmation_before_using_capacity_and_cadence(): void
    {
        $result = (new StrategicBudgetPlanBudgetReconciliation)->evaluate(new StrategicBudget([
            'assumptions' => ['opening_cash_balance' => 1_000],
            'computed' => ['runway_months' => 6, 'runway_open_ended' => false],
            'business_plan_sections' => [[
                'key' => 'goals',
                'title' => 'Goals',
                'financial_drivers' => [
                    $this->driver('opening_cash', 'Opening cash', 1_000),
                    $this->driver('runway_target', 'Minimum runway', 6),
                ],
            ], [
                'key' => 'market_customers',
                'title' => 'Market and customers',
                'financial_drivers' => [[
                    ...$this->driver('sales', 'Sales', 1_500, growthPercent: 10, capacity: 8),
                    'capacity_confirmed' => false,
                ]],
            ]],
            'revenue_forecast' => [[
                'label' => 'Sales',
                'amount' => 1_500,
                'growth_percent' => 10,
                'growth_cadence' => 'monthly',
                'growth_cadence_confirmed' => true,
                'monthly_capacity_units' => 8,
                'capacity_confirmed' => false,
                'plan_financial_driver_key' => 'sales',
            ]],
        ]));

        $this->assertSame('missing', $result['status']);
        $this->assertFalse($result['approval_available']);
        $this->assertSame('missing', $result['findings'][0]['severity']);
    }

    /**
     * @return array<string, bool|float|int|string>
     */
    private function driver(
        string $key,
        string $label,
        float|int $amount,
        string $cadence = 'monthly',
        bool $cadenceConfirmed = false,
        float|int $growthPercent = 0,
        string $growthCadence = 'monthly',
        ?float $capacity = null,
    ): array {
        return [
            'key' => $key,
            'category' => match ($key) {
                'opening_cash' => 'opening_cash',
                'runway_target' => 'runway_target',
                'owner_draw' => 'monthly_fixed_costs',
                default => 'revenue_forecast',
            },
            'label' => $label,
            'amount' => $amount,
            'quantity' => 1,
            'cadence' => $cadence,
            'cadence_confirmed' => $cadenceConfirmed,
            'growth_percent' => $growthPercent,
            'growth_cadence' => $growthCadence,
            'growth_cadence_confirmed' => true,
            'monthly_capacity_units' => $capacity,
            'capacity_confirmed' => $capacity !== null,
        ];
    }
}
