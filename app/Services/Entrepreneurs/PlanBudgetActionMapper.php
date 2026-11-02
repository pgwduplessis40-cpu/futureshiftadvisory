<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

/**
 * Only recognised, specific input corrections become client tasks.
 * Unrecognised warnings stay visible for an advisor to investigate.
 *
 * @phpstan-import-type Finding from PlanBudgetCoherence
 * @phpstan-import-type Action from PlanBudgetCoherence
 *
 * @phpstan-type Routing array{actions:list<Action>,advisor_reviews:list<Finding>,calculation_issues:list<Finding>}
 */
final class PlanBudgetActionMapper
{
    private const DESTINATION = 'Business Plan & Budget > Plan requirements (left side) > Financial assumptions > Budget editor';

    /** @param list<Finding> $findings
     * @return list<Action>
     */
    public function map(array $findings): array
    {
        return $this->route($findings)['actions'];
    }

    /** @param list<Finding> $findings
     * @return Routing
     */
    public function route(array $findings): array
    {
        $actions = [];
        $reviews = [];
        $calculations = [];
        foreach ($findings as $finding) {
            if (str_contains(strtolower($finding['message']), 'fixed-cost trace mismatch')) {
                $calculations[] = [
                    ...$finding,
                    'message' => str_replace(' Add the missing rows or relabel the table if it is only a subset.', '', $finding['message']),
                    'next_action' => 'Recalculate the saved Budget and reassess. If the totals still differ, investigate the calculation before finalising. The client does not need to change correct inputs.',
                ];

                continue;
            }
            $action = $this->clientAction($finding);
            if ($action === null) {
                $reviews[] = [
                    ...$finding,
                    'next_action' => 'Review the finding and the saved plan and Budget. Record why the plan can progress, or identify the exact input that needs clarification before requesting a client change.',
                ];

                continue;
            }
            $key = $action['key'];
            if (isset($actions[$key])) {
                $actions[$key]['steps'] = array_values(array_unique([...$actions[$key]['steps'], ...$action['steps']]));
                if ($action['severity'] === 'missing') {
                    $actions[$key]['severity'] = 'missing';
                }
            } else {
                $actions[$key] = $action;
            }
        }
        $order = ['regular_costs', 'one_off_costs', 'forecast_calendar', 'sales_forecast', 'funding_and_runway', 'plan_and_budget', 'budget_setup'];
        uksort($actions, fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));

        return ['actions' => array_values($actions), 'advisor_reviews' => $reviews, 'calculation_issues' => $calculations];
    }

    /** @param Finding $finding
     * @return Action|null
     */
    private function clientAction(array $finding): ?array
    {
        $message = $finding['message'];
        $text = strtolower($message);
        $key = 'budget_setup';
        $title = '';
        $area = '';
        $steps = [];

        if (preg_match('/^The plan refers to (.+?), but the budget does not name a matching cost row\\.$/i', $message, $matches) === 1) {
            $oneOff = str_contains(strtolower($matches[1]), 'trademark');
            $key = $oneOff ? 'one_off_costs' : 'regular_costs';
            $title = $oneOff ? 'Check the one-off business costs' : 'Identify the planned insurance cost';
            $area = $oneOff ? 'What one-off costs need funding?' : 'What will you pay every month even if sales are slow?';
            $steps = [
                'Your plan includes '.$matches[1].', but no Budget cost is named for it. Check whether an existing row covers it and rename that row if it does.',
                $oneOff
                    ? 'Only if it is absent, add it under one-off costs with the amount, quantity and forecast month when paid. Do not duplicate a one-off payment as a monthly cost.'
                    : 'Only if it is absent, add the expected amount and how often it is paid. An estimate is acceptable.',
            ];
        } elseif (preg_match('/^The plan describes "(.+)" as an annual cost, but the budget treats it as monthly\\.$/', $message, $matches) === 1) {
            $key = 'regular_costs';
            $title = 'Confirm the payment frequency for '.$matches[1];
            $area = 'What will you pay every month even if sales are slow?';
            $steps = [$message.' Is this payment annual or monthly? Set that row to the actual frequency and make the plan description agree.'];
        } elseif (str_starts_with($text, 'the plan states monthly operating costs of ')) {
            $key = 'regular_costs';
            $title = 'Confirm the monthly running-cost total';
            $area = 'What will you pay every month even if sales are slow?';
            $steps = [$message, 'Check which costs the written total includes. Update the written total if the saved cost rows are correct; correct a row only if its amount or payment frequency is wrong.'];
        } elseif (preg_match('/(?:billing cadence|payment frequency)[^:]*:\\s*(.+?)\\.$/i', $message, $matches) === 1) {
            $key = 'regular_costs';
            $title = 'Confirm how often the listed costs are paid';
            $area = 'What will you pay every month even if sales are slow?';
            $steps = ['For '.$matches[1].', check the payment frequency and tick “I have checked how often this cost is paid”. Your best estimate is acceptable.'];
        } elseif (str_contains($text, 'quantities that duplicate billing periods') && str_contains($message, ': ')) {
            $key = 'regular_costs';
            $title = 'Check the number of units for the listed costs';
            $area = 'What will you pay every month even if sales are slow?';
            $steps = [$message, 'For those rows, Qty means the number of subscriptions, people or licences paid for at the same time. Enter the payment frequency separately.'];
        } elseif (str_contains($text, 'forecast_start_month') || str_contains($text, 'set the forecast start month')) {
            $key = 'forecast_calendar';
            $title = 'Set and confirm the forecast start month';
            $area = 'Forecast start month';
            $steps = ['Set “Forecast start month” to the calendar month that should be Month 1 in your forecast.', 'Tick “I have checked Month 1 against the written milestones.”'];
        } elseif (preg_match('/(?:capacity for every revenue line|capacity for:|capacity for each affected revenue line|revenue beyond founder capacity|revenue growth is monthly or annual)[^:]*:\\s*(.+?)\\.$/i', $message, $matches) === 1) {
            $key = 'sales_forecast';
            $title = 'Confirm the named sales assumptions';
            $area = 'How will money come in?';
            $field = str_contains($text, 'contractor') ? 'the cost of contractors needed beyond your own delivery capacity'
                : (str_contains($text, 'growth') ? 'whether the growth rate applies each month or each year' : 'the maximum number of sales you can deliver per month');
            $steps = ['For '.$matches[1].', enter and confirm '.$field.'. Use your best current estimate.'];
        } elseif (preg_match('/^The budget allows .+ units per month for ".+", above the plan’s stated capacity of .+\\.$/', $message) === 1) {
            $key = 'sales_forecast';
            $title = 'Confirm the delivery capacity';
            $area = 'How will money come in?';
            $steps = [$message, 'Check how many sales you can deliver each month, then use that capacity in the named Budget row and written plan.'];
        } elseif (str_contains($text, 'confirm whether') && (str_contains($text, 'self-funded') || str_contains($text, 'external funding'))) {
            $key = 'funding_and_runway';
            $title = 'Confirm how the business will be funded';
            $area = 'Funding position';
            $steps = ['Select whether the business will use its own available cash or seek external funding. If seeking funding, describe what it will pay for.'];
        } elseif (str_starts_with($text, 'the plan states opening cash of ')) {
            $key = 'plan_and_budget';
            $title = 'Confirm the opening cash balance';
            $area = 'Opening cash balance';
            $steps = [$message, 'Check the cash available at the forecast start date and use that amount in both Financial assumptions and Opening cash balance.'];
        } elseif (str_contains($text, 'financial plan does not state a checkable') || str_starts_with($text, 'the submitted plan has no financial assumptions,')) {
            $key = 'plan_and_budget';
            $title = 'State the financial assumptions in the plan';
            $area = 'Financial assumptions (written plan)';
            $steps = ['In Financial assumptions, state the opening cash amount, how long it must last, how the business will be funded, and how many sales you can deliver each month. Use the assumptions already entered in the Budget.'];
        } elseif (str_starts_with($text, 'no budget evidence was captured')) {
            $title = 'Complete the Budget for assessment';
            $steps = ['Open Financial assumptions and complete the Budget with your planned costs, sales and available cash, then run reassessment.'];
        }

        if ($steps === []) {
            return null;
        }

        return [
            'key' => $key, 'severity' => $finding['severity'], 'title' => $title,
            'destination' => self::DESTINATION.($area !== '' ? ' > “'.$area.'”' : ''),
            'steps' => $steps,
        ];
    }
}
