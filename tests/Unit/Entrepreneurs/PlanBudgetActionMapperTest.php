<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Services\Entrepreneurs\PlanBudgetActionMapper;
use Tests\TestCase;

final class PlanBudgetActionMapperTest extends TestCase
{
    public function test_it_groups_each_supported_diagnostic_into_a_specific_founder_action(): void
    {
        $actions = (new PlanBudgetActionMapper)->map([
            $this->finding(
                'budget_support',
                'The model is using a monthly equivalent, but each of these costs still needs its billing cadence confirmed: Insurance, Commercial kitchen or site cost.',
            ),
            $this->finding(
                'plan_correlation',
                'The plan refers to professional indemnity insurance, but the budget does not name a matching cost row.',
            ),
            $this->finding(
                'plan_correlation',
                'The plan refers to trademark registration and protection, but the budget does not name a matching cost row.',
            ),
            $this->finding(
                'plan_correlation',
                'The plan states monthly operating costs of $600, but the budget uses $5,718 of monthly fixed costs.',
                severity: 'missing',
            ),
            $this->finding(
                'budget_support',
                'Verify current records for: forecast_start_month and opening cash payment timing.',
            ),
            $this->finding(
                'budget_support',
                'The forecast requires $4,686 of additional funding to cover the modelled cash trough.',
                severity: 'missing',
            ),
            $this->finding(
                'plan_correlation',
                'Runway needs checking: the expected runway is more than 2 months away from the budget calculation.',
            ),
            $this->finding(
                'plan_correlation',
                'Set a monthly delivery capacity for the revenue forecast.',
            ),
            $this->finding('plan_correlation', 'The plan and Budget use different payment dates.'),
            $this->finding('budget_support', 'Add the launch-date financial assumption.'),
        ]);

        $this->assertSame([
            'regular_costs',
            'forecast_calendar',
            'sales_forecast',
            'funding_and_runway',
            'plan_and_budget',
            'budget_setup',
        ], array_column($actions, 'key'));
        $this->assertSame('missing', $actions[0]['severity']);
        $this->assertSame(
            'Set the billing cadence for: Insurance, Commercial kitchen or site cost.',
            $actions[0]['steps'][0],
        );
        $this->assertStringContainsString(
            'Rename or split the row',
            implode(' ', $actions[0]['steps']),
        );
        $this->assertStringContainsString(
            'trademark registration and protection',
            implode(' ', $actions[0]['steps']),
        );
        $this->assertStringContainsString('$5,718', implode(' ', $actions[0]['steps']));
        $this->assertStringContainsString('opening cash', implode(' ', $actions[1]['steps']));
        $this->assertStringContainsString('additional funding', implode(' ', $actions[3]['steps']));
        $this->assertStringContainsString('Runway needs checking', implode(' ', $actions[3]['steps']));
        $this->assertStringContainsString('The plan and Budget use different payment dates.', implode(' ', $actions[4]['steps']));
        $this->assertStringContainsString('Add the launch-date financial assumption.', implode(' ', $actions[5]['steps']));
    }

    public function test_it_uses_the_general_cost_instruction_when_no_specific_cost_detail_can_be_extracted(): void
    {
        $actions = (new PlanBudgetActionMapper)->map([
            $this->finding('budget_support', 'Review the fixed cost assumptions before the next assessment.'),
            $this->finding('budget_support', 'Review the fixed cost assumptions before the next assessment.'),
        ]);

        $this->assertCount(1, $actions);
        $this->assertSame('regular_costs', $actions[0]['key']);
        $this->assertSame(
            'Review the regular cost rows identified in this assessment and correct the cost amount or cadence that is not yet aligned.',
            $actions[0]['steps'][0],
        );
    }

    /**
     * @return array{category:'budget_support'|'plan_correlation',severity:'missing'|'review',message:string,next_action:string}
     */
    private function finding(
        string $category,
        string $message,
        string $nextAction = 'Update the matching Budget input.',
        string $severity = 'review',
    ): array {
        return [
            'category' => $category,
            'severity' => $severity,
            'message' => $message,
            'next_action' => $nextAction,
        ];
    }
}
