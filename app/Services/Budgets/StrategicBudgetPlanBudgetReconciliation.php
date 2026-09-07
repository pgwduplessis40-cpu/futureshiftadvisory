<?php

declare(strict_types=1);

namespace App\Services\Budgets;

use App\Models\StrategicBudget;

/**
 * Reconciles confirmed financial assumptions in the business plan with the
 * forecast mechanics that the budget calculator applies. Traceability alone
 * cannot establish that an annual cost was not entered as a monthly cost, or
 * that a forecast respects the delivery capacity stated in the plan.
 *
 * @phpstan-type Finding array{severity:'missing'|'review',message:string,next_action:string}
 * @phpstan-type Reconciliation array{status:'met'|'review'|'missing',status_label:string,score:int,summary:string,evidence:list<string>,findings:list<Finding>,approval_available:bool,approval_message:string,confirmed_plan_assumption_count:int,checked_budget_row_count:int,unresolved_count:int}
 */
final class StrategicBudgetPlanBudgetReconciliation
{
    public const CATEGORY_OPENING_CASH = 'opening_cash';

    public const CATEGORY_RUNWAY_TARGET = 'runway_target';

    private const MONTHLY_FIXED_COSTS = 'monthly_fixed_costs';

    private const REVENUE_FORECAST = 'revenue_forecast';

    /**
     * @return Reconciliation
     */
    public function evaluate(StrategicBudget $budget): array
    {
        $drivers = $this->drivers($budget);
        $fixedRows = $this->budgetRows($budget, self::MONTHLY_FIXED_COSTS);
        $revenueRows = $this->budgetRows($budget, self::REVENUE_FORECAST);
        $findings = [];

        if ($drivers === []) {
            $findings[] = $this->finding(
                'missing',
                'No confirmed financial assumptions have been captured from the business plan.',
                'Capture the plan’s cost cadence, revenue capacity and growth basis before relying on the budget.',
            );
        }

        $this->reconcileOpeningCash($drivers, $budget, $findings);
        $this->reconcileRunwayTarget($drivers, $budget, $findings);

        foreach ($drivers as $driver) {
            if ($driver['category'] === self::MONTHLY_FIXED_COSTS) {
                $this->reconcileRecurringCost($driver, $fixedRows, $findings);
            }

            if ($driver['category'] === self::REVENUE_FORECAST) {
                $this->reconcileRevenueDriver($driver, $revenueRows, $findings);
            }
        }

        $unresolvedCount = count($findings);
        $missingCount = count(array_filter($findings, fn (array $finding): bool => $finding['severity'] === 'missing'));
        $checkedRows = count($fixedRows) + count($revenueRows);
        $checks = max(1, count($drivers) + $checkedRows);
        $score = max(0, min(100, (int) round((1 - ($unresolvedCount / $checks)) * 100)));
        $approvalAvailable = $drivers !== [] && $unresolvedCount === 0;
        $status = $approvalAvailable ? 'met' : ($missingCount > 0 ? 'missing' : 'review');

        return [
            'status' => $status,
            'status_label' => match ($status) {
                'met' => 'Reconciled',
                'missing' => 'Blocked — assumptions needed',
                default => 'Blocked — resolve differences',
            },
            'score' => $score,
            'summary' => $approvalAvailable
                ? 'Confirmed plan assumptions agree with the forecast cadence, capacity, cash and runway mechanics.'
                : $unresolvedCount.' plan-to-budget assumption '.($unresolvedCount === 1 ? 'needs' : 'need').' resolution before advisor approval.',
            'evidence' => [
                count($drivers).' plan financial assumption'.(count($drivers) === 1 ? ' is' : 's are').' available for reconciliation.',
                $checkedRows.' recurring-cost and revenue forecast row'.($checkedRows === 1 ? ' was' : 's were').' checked.',
                $unresolvedCount === 0
                    ? 'Cadence, capacity, growth, opening cash and runway agree with the confirmed plan assumptions.'
                    : $unresolvedCount.' unresolved financial assumption '.($unresolvedCount === 1 ? 'remains.' : 'issues remain.'),
            ],
            'findings' => $findings,
            'approval_available' => $approvalAvailable,
            'approval_message' => $approvalAvailable
                ? 'Plan financial assumptions and budget mechanics are reconciled.'
                : 'Resolve the Plan–budget assumptions findings before advisor approval.',
            'confirmed_plan_assumption_count' => count($drivers),
            'checked_budget_row_count' => $checkedRows,
            'unresolved_count' => $unresolvedCount,
        ];
    }

    /**
     * @param  Reconciliation  $reconciliation
     * @return array{key:'plan_budget_reconciliation',title:'Plan–budget assumptions reconciliation',status:'met'|'review'|'missing',status_label:'Met'|'Missing'|'Needs review',score:int,summary:string,evidence:list<string>,blocking:bool,findings:list<Finding>}
     */
    public function criterion(array $reconciliation): array
    {
        return [
            'key' => 'plan_budget_reconciliation',
            'title' => 'Plan–budget assumptions reconciliation',
            'status' => $reconciliation['status'],
            'status_label' => match ($reconciliation['status']) {
                'met' => 'Met',
                'missing' => 'Missing',
                default => 'Needs review',
            },
            'score' => $reconciliation['score'],
            'summary' => $reconciliation['summary'],
            'evidence' => $reconciliation['evidence'],
            'blocking' => ! $reconciliation['approval_available'],
            'findings' => $reconciliation['findings'],
        ];
    }

    /**
     * @param  list<array{key:string,category:string,label:string,amount:float,quantity:float,cadence:string,cadence_confirmed:bool,growth_percent:float,growth_cadence:string,growth_cadence_confirmed:bool,monthly_capacity_units:float|null,capacity_confirmed:bool,section_title:string}>  $drivers
     * @param  list<Finding>  $findings
     */
    private function reconcileOpeningCash(array $drivers, StrategicBudget $budget, array &$findings): void
    {
        $cashAssumption = $this->firstDriver($drivers, self::CATEGORY_OPENING_CASH);
        if ($cashAssumption === null) {
            $findings[] = $this->finding(
                'missing',
                'The plan’s opening-cash position has not been captured for reconciliation.',
                'Record the opening cash stated in the plan and confirm it against the budget’s opening-cash assumption.',
            );

            return;
        }

        $budgetCash = (float) data_get($budget->assumptions, 'opening_cash_balance', 0);
        if (! $this->withinTolerance($cashAssumption['amount'], $budgetCash)) {
            $findings[] = $this->finding(
                'review',
                sprintf(
                    'The plan records opening cash of %s, but the budget starts with %s.',
                    $this->money($cashAssumption['amount']),
                    $this->money($budgetCash),
                ),
                'Use the verified opening-cash balance in both the plan assumption and budget settings.',
            );
        }
    }

    /**
     * @param  list<array{key:string,category:string,label:string,amount:float,quantity:float,cadence:string,cadence_confirmed:bool,growth_percent:float,growth_cadence:string,growth_cadence_confirmed:bool,monthly_capacity_units:float|null,capacity_confirmed:bool,section_title:string}>  $drivers
     * @param  list<Finding>  $findings
     */
    private function reconcileRunwayTarget(array $drivers, StrategicBudget $budget, array &$findings): void
    {
        $runwayTarget = $this->firstDriver($drivers, self::CATEGORY_RUNWAY_TARGET);
        if ($runwayTarget === null) {
            $findings[] = $this->finding(
                'missing',
                'The plan’s runway target has not been captured for reconciliation.',
                'Record the minimum runway stated in the plan before relying on the forecast.',
            );

            return;
        }

        $targetMonths = $runwayTarget['amount'];
        $runwayOpenEnded = (bool) data_get($budget->computed, 'runway_open_ended', false);
        $actualMonths = data_get($budget->computed, 'runway_months');
        if ($runwayOpenEnded || (is_numeric($actualMonths) && (float) $actualMonths >= $targetMonths)) {
            return;
        }

        $actualLabel = is_numeric($actualMonths) ? (string) (int) $actualMonths.' months' : 'no visible runway';
        $findings[] = $this->finding(
            'review',
            sprintf(
                'The plan targets %d months of runway, but the budget shows %s.',
                (int) round($targetMonths),
                $actualLabel,
            ),
            'Correct the cost, revenue, cash or funding assumptions until the forecast supports the stated runway target.',
        );
    }

    /**
     * @param  array{key:string,category:string,label:string,amount:float,quantity:float,cadence:string,cadence_confirmed:bool,growth_percent:float,growth_cadence:string,growth_cadence_confirmed:bool,monthly_capacity_units:float|null,capacity_confirmed:bool,section_title:string}  $driver
     * @param  list<array{label:string,amount:float,quantity:float,cadence:string,cadence_confirmed:bool,growth_percent:float,growth_cadence:string,growth_cadence_confirmed:bool,monthly_capacity_units:float|null,capacity_confirmed:bool,plan_financial_driver_key:string}>  $rows
     * @param  list<Finding>  $findings
     */
    private function reconcileRecurringCost(array $driver, array $rows, array &$findings): void
    {
        $linkedRows = $this->linkedRows($rows, $driver['key']);
        if ($linkedRows === []) {
            return;
        }

        if (! $driver['cadence_confirmed']) {
            $findings[] = $this->finding(
                'missing',
                sprintf('The plan does not confirm whether "%s" is weekly, monthly or annual.', $driver['label']),
                'Confirm the cost cadence from the business plan before comparing it with the budget.',
            );
        }

        foreach ($linkedRows as $row) {
            if (! $row['cadence_confirmed']) {
                $findings[] = $this->finding(
                    'missing',
                    sprintf('The budget does not confirm the cadence for "%s".', $row['label']),
                    'Confirm whether this budget row is weekly, monthly, quarterly or annual.',
                );
            }
        }

        if (! $driver['cadence_confirmed'] || collect($linkedRows)->contains(fn (array $row): bool => ! $row['cadence_confirmed'])) {
            return;
        }

        $planMonthly = $this->monthlyValue($driver['amount'], $driver['quantity'], $driver['cadence']);
        $budgetMonthly = array_sum(array_map(
            fn (array $row): float => $this->monthlyValue($row['amount'], $row['quantity'], $row['cadence']),
            $linkedRows,
        ));
        if ($this->withinTolerance($planMonthly, $budgetMonthly)) {
            return;
        }

        $findings[] = $this->finding(
            'review',
            sprintf(
                'The plan’s "%s" is %s per month after cadence normalisation, but the linked budget rows total %s per month.',
                $driver['label'],
                $this->money($planMonthly),
                $this->money($budgetMonthly),
            ),
            'Correct the cadence or amount so the monthly operating-cost basis agrees within 5%.',
        );
    }

    /**
     * @param  array{key:string,category:string,label:string,amount:float,quantity:float,cadence:string,cadence_confirmed:bool,growth_percent:float,growth_cadence:string,growth_cadence_confirmed:bool,monthly_capacity_units:float|null,capacity_confirmed:bool,section_title:string}  $driver
     * @param  list<array{label:string,amount:float,quantity:float,cadence:string,cadence_confirmed:bool,growth_percent:float,growth_cadence:string,growth_cadence_confirmed:bool,monthly_capacity_units:float|null,capacity_confirmed:bool,plan_financial_driver_key:string}>  $rows
     * @param  list<Finding>  $findings
     */
    private function reconcileRevenueDriver(array $driver, array $rows, array &$findings): void
    {
        $linkedRows = $this->linkedRows($rows, $driver['key']);
        if ($linkedRows === []) {
            return;
        }

        if (! $driver['capacity_confirmed'] || $driver['monthly_capacity_units'] === null) {
            $findings[] = $this->finding(
                'missing',
                sprintf('The plan does not confirm the monthly delivery capacity for "%s".', $driver['label']),
                'Record the maximum units the plan allows each month before relying on this revenue forecast.',
            );
        }

        if (! $driver['growth_cadence_confirmed']) {
            $findings[] = $this->finding(
                'missing',
                sprintf('The plan does not confirm whether growth for "%s" is monthly or annual.', $driver['label']),
                'Confirm the growth percentage and its cadence from the business plan.',
            );
        }

        foreach ($linkedRows as $row) {
            if (! $row['capacity_confirmed'] || $row['monthly_capacity_units'] === null) {
                $findings[] = $this->finding(
                    'missing',
                    sprintf('The budget does not set a confirmed monthly capacity for "%s".', $row['label']),
                    'Set and confirm the delivery capacity that constrains this revenue line.',
                );
            }
            if (! $row['growth_cadence_confirmed']) {
                $findings[] = $this->finding(
                    'missing',
                    sprintf('The budget does not confirm whether growth for "%s" is monthly or annual.', $row['label']),
                    'Confirm the growth cadence used in the forecast.',
                );
            }
        }

        if (! $driver['capacity_confirmed'] || $driver['monthly_capacity_units'] === null || ! $driver['growth_cadence_confirmed']) {
            return;
        }

        foreach ($linkedRows as $row) {
            if (! $row['capacity_confirmed'] || $row['monthly_capacity_units'] === null || ! $row['growth_cadence_confirmed']) {
                continue;
            }

            if ($row['monthly_capacity_units'] > $driver['monthly_capacity_units']) {
                $findings[] = $this->finding(
                    'review',
                    sprintf(
                        'The budget allows %.2f %s per month for "%s", above the plan’s confirmed capacity of %.2f.',
                        $row['monthly_capacity_units'],
                        $this->unitLabel($driver['quantity']),
                        $driver['label'],
                        $driver['monthly_capacity_units'],
                    ),
                    'Reduce the budget capacity or revise the plan with evidence for the higher delivery capacity.',
                );
            }

            if (! $this->withinTolerance($driver['growth_percent'], $row['growth_percent']) || $driver['growth_cadence'] !== $row['growth_cadence']) {
                $findings[] = $this->finding(
                    'review',
                    sprintf(
                        'The plan models "%s" growth as %s %s, but the budget applies %s %s.',
                        $driver['label'],
                        $this->percent($driver['growth_percent']),
                        $driver['growth_cadence'],
                        $this->percent($row['growth_percent']),
                        $row['growth_cadence'],
                    ),
                    'Use the plan’s confirmed growth rate and cadence, or update the plan with support for the forecast change.',
                );
            }
        }
    }

    /**
     * @return list<array{key:string,category:string,label:string,amount:float,quantity:float,cadence:string,cadence_confirmed:bool,growth_percent:float,growth_cadence:string,growth_cadence_confirmed:bool,monthly_capacity_units:float|null,capacity_confirmed:bool,section_title:string}>
     */
    private function drivers(StrategicBudget $budget): array
    {
        $drivers = [];

        foreach ((array) ($budget->business_plan_sections ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }

            $sectionTitle = trim((string) ($section['title'] ?? $section['key'] ?? 'Business plan'));
            foreach ((array) ($section['financial_drivers'] ?? []) as $driver) {
                if (! is_array($driver)) {
                    continue;
                }

                $key = trim((string) ($driver['key'] ?? ''));
                $category = trim((string) ($driver['category'] ?? ''));
                $label = trim((string) ($driver['label'] ?? ''));
                $rawAmount = $driver['amount'] ?? null;
                $amount = max(0.0, (float) $rawAmount);
                $isCashOrRunway = in_array($category, [self::CATEGORY_OPENING_CASH, self::CATEGORY_RUNWAY_TARGET], true);
                if ($key === '' || $label === '' || ! is_numeric($rawAmount) || (! $isCashOrRunway && $amount <= 0)) {
                    continue;
                }

                $drivers[] = [
                    'key' => $key,
                    'category' => $category,
                    'label' => $label,
                    'amount' => $amount,
                    'quantity' => max(1.0, (float) ($driver['quantity'] ?? 1)),
                    'cadence' => $this->cadence($driver['cadence'] ?? null),
                    'cadence_confirmed' => (bool) ($driver['cadence_confirmed'] ?? false),
                    'growth_percent' => max(-100.0, min(500.0, (float) ($driver['growth_percent'] ?? 0))),
                    'growth_cadence' => $this->growthCadence($driver['growth_cadence'] ?? null),
                    'growth_cadence_confirmed' => (bool) ($driver['growth_cadence_confirmed'] ?? false),
                    'monthly_capacity_units' => is_numeric($driver['monthly_capacity_units'] ?? null)
                        ? max(0.0, (float) $driver['monthly_capacity_units'])
                        : null,
                    'capacity_confirmed' => (bool) ($driver['capacity_confirmed'] ?? false),
                    'section_title' => $sectionTitle === '' ? 'Business plan' : $sectionTitle,
                ];
            }
        }

        return $drivers;
    }

    /**
     * @return list<array{label:string,amount:float,quantity:float,cadence:string,cadence_confirmed:bool,growth_percent:float,growth_cadence:string,growth_cadence_confirmed:bool,monthly_capacity_units:float|null,capacity_confirmed:bool,plan_financial_driver_key:string}>
     */
    private function budgetRows(StrategicBudget $budget, string $attribute): array
    {
        $rows = [];
        foreach ((array) ($budget->{$attribute} ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $amount = max(0.0, (float) ($row['amount'] ?? 0));
            if ($amount <= 0) {
                continue;
            }

            $rows[] = [
                'label' => trim((string) ($row['label'] ?? 'Budget row')),
                'amount' => $amount,
                'quantity' => max(1.0, (float) ($row['quantity'] ?? 1)),
                'cadence' => $this->cadence($row['cadence'] ?? null),
                'cadence_confirmed' => (bool) ($row['cadence_confirmed'] ?? false),
                'growth_percent' => max(-100.0, min(500.0, (float) ($row['growth_percent'] ?? $row['monthly_growth_percent'] ?? 0))),
                'growth_cadence' => $this->growthCadence($row['growth_cadence'] ?? null),
                'growth_cadence_confirmed' => (bool) ($row['growth_cadence_confirmed'] ?? false),
                'monthly_capacity_units' => is_numeric($row['monthly_capacity_units'] ?? null)
                    ? max(0.0, (float) $row['monthly_capacity_units'])
                    : null,
                'capacity_confirmed' => (bool) ($row['capacity_confirmed'] ?? false),
                'plan_financial_driver_key' => trim((string) ($row['plan_financial_driver_key'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{key:string,category:string,label:string,amount:float,quantity:float,cadence:string,cadence_confirmed:bool,growth_percent:float,growth_cadence:string,growth_cadence_confirmed:bool,monthly_capacity_units:float|null,capacity_confirmed:bool,section_title:string}>  $drivers
     * @return array{key:string,category:string,label:string,amount:float,quantity:float,cadence:string,cadence_confirmed:bool,growth_percent:float,growth_cadence:string,growth_cadence_confirmed:bool,monthly_capacity_units:float|null,capacity_confirmed:bool,section_title:string}|null
     */
    private function firstDriver(array $drivers, string $category): ?array
    {
        foreach ($drivers as $driver) {
            if ($driver['category'] === $category) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * @template T of array{plan_financial_driver_key:string}
     * @param  list<T>  $rows
     * @return list<T>
     */
    private function linkedRows(array $rows, string $driverKey): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $row['plan_financial_driver_key'] === $driverKey,
        ));
    }

    /** @return Finding */
    private function finding(string $severity, string $message, string $nextAction): array
    {
        return [
            'severity' => $severity === 'missing' ? 'missing' : 'review',
            'message' => $message,
            'next_action' => $nextAction,
        ];
    }

    private function monthlyValue(float $amount, float $quantity, string $cadence): float
    {
        $value = $amount * $quantity;

        return match ($cadence) {
            'weekly' => $value * (52 / 12),
            'fortnightly' => $value * (26 / 12),
            'quarterly' => $value / 3,
            'annual' => $value / 12,
            default => $value,
        };
    }

    private function cadence(mixed $value): string
    {
        return in_array($value, ['weekly', 'fortnightly', 'monthly', 'quarterly', 'annual'], true)
            ? (string) $value
            : 'monthly';
    }

    private function growthCadence(mixed $value): string
    {
        return in_array($value, ['monthly', 'annual'], true) ? (string) $value : 'monthly';
    }

    private function withinTolerance(float $expected, float $actual): bool
    {
        return abs($expected - $actual) <= max(1.0, abs($expected) * 0.05);
    }

    private function money(float $amount): string
    {
        return '$'.number_format($amount, 0, '.', ',');
    }

    private function percent(float $value): string
    {
        return number_format($value, 2, '.', '').'%';
    }

    private function unitLabel(float $quantity): string
    {
        return $quantity === 1.0 ? 'unit' : 'units';
    }
}
