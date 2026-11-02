<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Services\Entrepreneurs\PlanBudgetActionMapper;
use Tests\TestCase;

final class PlanBudgetActionMapperTest extends TestCase
{
    public function test_vague_warnings_and_derived_results_are_advisor_reviews_not_client_tasks(): void
    {
        $messages = [
            'Review the fixed cost assumptions before the next assessment.',
            'The forecast requires $4,686 of additional funding to cover the modelled cash trough.',
            'Runway needs checking: the expected runway is more than 2 months away from the budget calculation.',
            'The plan and Budget use different payment dates.',
        ];
        $result = (new PlanBudgetActionMapper)->route(array_map($this->finding(...), $messages));
        $this->assertSame([], $result['actions']);
        $this->assertSame([], $result['calculation_issues']);
        $this->assertSame($messages, array_column($result['advisor_reviews'], 'message'));
        $this->assertStringContainsString('exact input', $result['advisor_reviews'][0]['next_action']);
    }

    public function test_calculation_mismatch_keeps_the_diagnostic_and_is_never_a_client_correction(): void
    {
        $message = 'Fixed-cost trace mismatch: saved total $5,000 differs from the traced $4,000.';
        $result = (new PlanBudgetActionMapper)->route([$this->finding($message)]);
        $this->assertSame([], $result['actions']);
        $this->assertSame([], $result['advisor_reviews']);
        $this->assertSame($message, $result['calculation_issues'][0]['message']);
        $this->assertStringContainsString('Recalculate the saved Budget', $result['calculation_issues'][0]['next_action']);
    }

    public function test_named_corrections_explain_the_actual_input_without_inventing_costs(): void
    {
        $result = (new PlanBudgetActionMapper)->route([
            $this->finding('The plan describes "Insurance" as an annual cost, but the budget treats it as monthly.'),
            $this->finding('The plan refers to trademark registration and protection, but the budget does not name a matching cost row.'),
            $this->finding('Verify current records for: forecast_start_month.'),
            $this->finding('Confirm delivery capacity for every revenue line: Consulting.'),
        ]);
        $this->assertSame(['regular_costs', 'one_off_costs', 'forecast_calendar', 'sales_forecast'], array_column($result['actions'], 'key'));
        $this->assertSame([], $result['advisor_reviews']);
        $this->assertStringContainsString('Insurance', $result['actions'][0]['steps'][0]);
        $this->assertStringContainsString('annual or monthly', $result['actions'][0]['steps'][0]);
        $this->assertStringContainsString('Only if it is absent', $result['actions'][1]['steps'][1]);
        $this->assertStringContainsString('Do not duplicate a one-off payment', $result['actions'][1]['steps'][1]);
        $this->assertStringContainsString('What one-off costs need funding?', $result['actions'][1]['destination']);
        $this->assertStringContainsString('Consulting', $result['actions'][3]['steps'][0]);
        $this->assertStringContainsString('maximum number of sales', $result['actions'][3]['steps'][0]);
    }

    public function test_monthly_total_disagreement_allows_the_written_plan_to_be_corrected(): void
    {
        $message = 'The plan states monthly operating costs of $600, while the Budget totals $5,718 per month.';
        $actions = (new PlanBudgetActionMapper)->map([$this->finding($message), $this->finding($message)]);
        $this->assertCount(1, $actions);
        $this->assertCount(2, $actions[0]['steps']);
        $this->assertSame($message, $actions[0]['steps'][0]);
        $this->assertStringContainsString('Update the written total if the saved cost rows are correct', $actions[0]['steps'][1]);
    }

    /** @return array{category:'budget_support',severity:'review',message:string,next_action:string} */
    private function finding(string $message): array
    {
        return ['category' => 'budget_support', 'severity' => 'review', 'message' => $message, 'next_action' => 'Old generic advice.'];
    }
}
