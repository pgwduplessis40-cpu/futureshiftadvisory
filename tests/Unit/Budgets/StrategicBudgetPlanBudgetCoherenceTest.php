<?php

declare(strict_types=1);

namespace Tests\Unit\Budgets;

use App\Models\StrategicBudget;
use App\Services\Budgets\StrategicBudgetPlanBudgetCoherence;
use Tests\TestCase;

final class StrategicBudgetPlanBudgetCoherenceTest extends TestCase
{
    public function test_it_requires_two_way_links_between_material_plan_drivers_and_budget_rows(): void
    {
        $result = (new StrategicBudgetPlanBudgetCoherence)->evaluate(new StrategicBudget([
            'business_plan_sections' => [[
                'key' => 'market_customers',
                'title' => 'Market and customers',
                'financial_drivers' => [[
                    'key' => 'driver_enterprise_sales',
                    'category' => 'revenue_forecast',
                    'label' => 'Enterprise sales',
                    'amount' => 10_000,
                    'quantity' => 4,
                    'month' => 3,
                ]],
            ]],
            'revenue_forecast' => [[
                'label' => 'Enterprise sales',
                'amount' => 10_000,
                'quantity' => 3,
                'month' => 1,
                'plan_financial_driver_key' => 'driver_enterprise_sales',
            ], [
                'label' => 'Unexplained upsell',
                'amount' => 12_000,
                'quantity' => 1,
            ]],
        ]));

        $this->assertSame('missing', $result['status']);
        $this->assertFalse($result['approval_available']);
        $this->assertSame(1, $result['linked_driver_count']);
        $this->assertSame(2, $result['material_row_count']);
        $this->assertStringContainsString('$40,000', $result['findings'][0]['message']);
        $this->assertStringContainsString('starts in month 3', $result['findings'][1]['message']);
        $this->assertStringContainsString('Unexplained upsell', $result['findings'][2]['message']);
    }

    public function test_it_accepts_aligned_amounts_and_timing(): void
    {
        $result = (new StrategicBudgetPlanBudgetCoherence)->evaluate(new StrategicBudget([
            'business_plan_sections' => [[
                'key' => 'operations',
                'title' => 'Operations',
                'financial_drivers' => [[
                    'key' => 'driver_delivery_team',
                    'category' => 'monthly_fixed_costs',
                    'label' => 'Delivery team',
                    'amount' => 7_500,
                    'quantity' => 2,
                    'month' => 1,
                ]],
            ]],
            'monthly_fixed_costs' => [[
                'label' => 'Delivery team',
                'amount' => 7_500,
                'quantity' => 2,
                'plan_financial_driver_key' => 'driver_delivery_team',
            ]],
        ]));

        $this->assertSame('met', $result['status']);
        $this->assertTrue($result['approval_available']);
        $this->assertSame(100, $result['score']);
        $this->assertSame([], $result['findings']);
    }
}
