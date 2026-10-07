<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

/**
 * Converts detailed plan-budget diagnostics into the small number of distinct
 * founder actions that resolve them. The diagnostics remain on the assessment
 * for auditability; this map is the single action list used by the assessment
 * screen and the founder change-request message.
 *
 * @phpstan-type Finding array{category:'budget_support'|'plan_correlation',severity:'missing'|'review',message:string,next_action:string}
 * @phpstan-type Action array{key:'regular_costs'|'forecast_calendar'|'funding_and_runway'|'sales_forecast'|'plan_and_budget'|'budget_setup',severity:'missing'|'review',title:string,destination:string,steps:list<string>,completion:string}
 */
final class PlanBudgetActionMapper
{
    /** @var array<string, int> */
    private const ACTION_ORDER = [
        'regular_costs' => 10,
        'forecast_calendar' => 20,
        'sales_forecast' => 30,
        'funding_and_runway' => 40,
        'plan_and_budget' => 50,
        'budget_setup' => 60,
    ];

    /**
     * @param  list<Finding>  $findings
     * @return list<Action>
     */
    public function map(array $findings): array
    {
        /** @var array<string, list<Finding>> $groups */
        $groups = [];
        foreach ($findings as $finding) {
            $groups[$this->actionKey($finding)][] = $finding;
        }

        uksort(
            $groups,
            fn (string $left, string $right): int => self::ACTION_ORDER[$left] <=> self::ACTION_ORDER[$right],
        );

        return array_map(
            fn (array $group, string $key): array => $this->action($key, $group),
            $groups,
            array_keys($groups),
        );
    }

    /** @param Finding $finding */
    private function actionKey(array $finding): string
    {
        $text = strtolower($finding['message'].' '.$finding['next_action']);

        if (str_contains($text, 'forecast_start_month')
            || str_contains($text, 'forecast start month')
            || str_contains($text, 'forecast calendar')) {
            return 'forecast_calendar';
        }

        if (str_contains($text, 'fixed cost')
            || str_contains($text, 'operating cost')
            || str_contains($text, 'recurring cost')
            || str_contains($text, 'cadence')
            || str_contains($text, 'insurance')
            || str_contains($text, 'trademark')
            || str_contains($text, 'owner compensation')) {
            return 'regular_costs';
        }

        if (str_contains($text, 'funding')
            || str_contains($text, 'runway')
            || str_contains($text, 'cash trough')
            || str_contains($text, 'break-even')
            || str_contains($text, 'opening cash')) {
            return 'funding_and_runway';
        }

        if (str_contains($text, 'revenue')
            || str_contains($text, 'capacity')
            || str_contains($text, 'contractor')) {
            return 'sales_forecast';
        }

        return $finding['category'] === 'plan_correlation'
            ? 'plan_and_budget'
            : 'budget_setup';
    }

    /**
     * @param  Action['key']  $key
     * @param  list<Finding>  $findings
     * @return Action
     */
    private function action(string $key, array $findings): array
    {
        $messages = $this->messages($findings);
        $severity = collect($findings)->contains(
            fn (array $finding): bool => $finding['severity'] === 'missing',
        ) ? 'missing' : 'review';

        $action = match ($key) {
            'regular_costs' => $this->regularCostsAction($messages),
            'forecast_calendar' => $this->forecastCalendarAction($messages),
            'sales_forecast' => $this->salesForecastAction($messages),
            'funding_and_runway' => $this->fundingAndRunwayAction($messages),
            'plan_and_budget' => $this->planAndBudgetAction($messages),
            default => $this->budgetSetupAction($messages),
        };

        return [
            'key' => $key,
            'severity' => $severity,
            ...$action,
        ];
    }

    /**
     * @param  list<Finding>  $findings
     * @return list<string>
     */
    private function messages(array $findings): array
    {
        return collect($findings)
            ->pluck('message')
            ->map(fn (string $message): string => trim($message))
            ->filter()
            ->unique(fn (string $message): string => strtolower($message))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $messages
     * @return array{title:string,destination:string,steps:list<string>,completion:string}
     */
    private function regularCostsAction(array $messages): array
    {
        $steps = [];
        $cadenceRows = $this->cadenceRows($messages);
        if ($cadenceRows !== []) {
            $steps[] = 'Set the billing cadence for: '.implode(', ', $cadenceRows).'.';
        }

        $missingCosts = $this->missingCostRows($messages);
        $genericInsurance = collect($cadenceRows)->contains(
            fn (string $row): bool => strtolower(trim($row)) === 'insurance',
        );
        if (in_array('professional indemnity insurance', $missingCosts, true) && $genericInsurance) {
            $steps[] = 'Confirm whether “Insurance” covers professional indemnity insurance. Rename or split the row if it does; otherwise add a separate professional-indemnity insurance row.';
            $missingCosts = array_values(array_diff($missingCosts, ['professional indemnity insurance']));
        }

        if ($missingCosts !== []) {
            $steps[] = 'Add a cost row for: '.implode(', ', $missingCosts).'.';
        }

        $costMismatch = collect($messages)
            ->first(fn (string $message): bool => str_contains(strtolower($message), 'monthly operating costs'));
        if (is_string($costMismatch)) {
            $steps[] = 'Make the plan and Budget use the same monthly cost total. The assessment currently shows: '.$costMismatch;
        }

        if ($steps === []) {
            $steps[] = 'Review the regular cost rows identified in this assessment and correct the cost amount or cadence that is not yet aligned.';
        }

        return [
            'title' => 'Reconcile the regular business costs',
            'destination' => 'Budget > Monthly fixed costs, then Business plan > Financial assumptions',
            'steps' => $steps,
            'completion' => 'Save both sections, then rerun the assessment.',
        ];
    }

    /**
     * @param  list<string>  $messages
     * @return array{title:string,destination:string,steps:list<string>,completion:string}
     */
    private function forecastCalendarAction(array $messages): array
    {
        $steps = [
            'Set “Forecast start month” to the month that should be Month 1 in your forecast.',
            'Tick “I have checked Month 1 against the written milestones.”',
        ];

        if (collect($messages)->contains(
            fn (string $message): bool => str_contains(strtolower($message), 'opening cash')
                || str_contains(strtolower($message), 'payment timing'),
        )) {
            $steps[] = 'Confirm the opening cash and customer/supplier payment timing used for Month 1.';
        }

        return [
            'title' => 'Set and confirm the forecast start month',
            'destination' => 'Budget > Financial assumptions',
            'steps' => $steps,
            'completion' => 'Save the Financial assumptions section.',
        ];
    }

    /**
     * @param  list<string>  $messages
     * @return array{title:string,destination:string,steps:list<string>,completion:string}
     */
    private function fundingAndRunwayAction(array $messages): array
    {
        $steps = [
            'First save the cost and forecast-date changes above, then rerun the assessment so the funding result is recalculated.',
        ];

        foreach ($messages as $message) {
            $normalised = strtolower($message);
            if (str_contains($normalised, 'additional funding') || str_contains($normalised, 'funding gap')) {
                $steps[] = 'If the recalculated forecast still needs funding, record the source, amount, and expected date. Current result: '.$message;
                break;
            }
        }

        foreach ($messages as $message) {
            if (str_contains(strtolower($message), 'runway')) {
                $steps[] = 'Check the intended runway against the recalculated runway before changing the plan statement. Current result: '.$message;
                break;
            }
        }

        return [
            'title' => 'Recheck funding and runway after the Budget corrections',
            'destination' => 'Budget > Funding and runway',
            'steps' => $steps,
            'completion' => 'Save the funding position only after the recalculated result is confirmed.',
        ];
    }

    /**
     * @param  list<string>  $messages
     * @return array{title:string,destination:string,steps:list<string>,completion:string}
     */
    private function salesForecastAction(array $messages): array
    {
        return [
            'title' => 'Confirm the sales forecast assumptions',
            'destination' => 'Budget > Revenue forecast',
            'steps' => [
                'For each affected revenue line, set the expected sales, price, payment timing, and monthly delivery capacity.',
                'Use the assessment detail to correct the named revenue line before changing unrelated estimates.',
            ],
            'completion' => 'Save the Revenue forecast section.',
        ];
    }

    /**
     * @param  list<string>  $messages
     * @return array{title:string,destination:string,steps:list<string>,completion:string}
     */
    private function planAndBudgetAction(array $messages): array
    {
        return [
            'title' => 'Make the plan and Budget use the same figures',
            'destination' => 'Business plan > Financial assumptions, Revenue model, and Funding and support',
            'steps' => [
                'Update the matching plan statement and Budget row so both use the same amount, timing, and cadence.',
                ...$this->assessmentDetailSteps($messages),
            ],
            'completion' => 'Save the plan section and the Budget.',
        ];
    }

    /**
     * @param  list<string>  $messages
     * @return array{title:string,destination:string,steps:list<string>,completion:string}
     */
    private function budgetSetupAction(array $messages): array
    {
        return [
            'title' => 'Complete the Budget inputs needed for this assessment',
            'destination' => 'Budget > Financial assumptions',
            'steps' => [
                'Complete the specific Budget input identified in the assessment using the best information available today.',
                ...$this->assessmentDetailSteps($messages),
            ],
            'completion' => 'Save the Budget, then rerun the assessment.',
        ];
    }

    /**
     * @param  list<string>  $messages
     * @return list<string>
     */
    private function assessmentDetailSteps(array $messages): array
    {
        return collect($messages)
            ->take(2)
            ->map(fn (string $message): string => 'Assessment detail: '.$message)
            ->all();
    }

    /**
     * @param  list<string>  $messages
     * @return list<string>
     */
    private function cadenceRows(array $messages): array
    {
        $rows = [];
        foreach ($messages as $message) {
            if (preg_match('/billing cadence[^:]*:\s*(.+?)\.$/i', $message, $matches) !== 1) {
                continue;
            }

            foreach (preg_split('/\s*,\s*/', trim($matches[1])) ?: [] as $row) {
                $row = trim($row);
                if ($row !== '') {
                    $rows[] = $row;
                }
            }
        }

        return $this->uniqueStrings($rows);
    }

    /**
     * @param  list<string>  $messages
     * @return list<string>
     */
    private function missingCostRows(array $messages): array
    {
        $rows = [];
        foreach ($messages as $message) {
            if (preg_match('/^The plan refers to (.+?), but the budget does not name a matching cost row\.$/i', $message, $matches) === 1) {
                $rows[] = trim($matches[1]);
            }
        }

        return $this->uniqueStrings(array_filter($rows));
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            $key = strtolower($value);
            if ($key !== '' && ! array_key_exists($key, $unique)) {
                $unique[$key] = $value;
            }
        }

        return array_values($unique);
    }
}
