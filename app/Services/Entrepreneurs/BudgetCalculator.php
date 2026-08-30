<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Support\Methodology\ProvidesMethodology;

final class BudgetCalculator implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.budget_forecast'];
    }

    private const MONTHS_PER_YEAR = 12;

    private const DEFAULT_FORECAST_YEARS = 3;

    private const SUPPORTED_FORECAST_YEARS = [1, 2, 3, 5];

    /**
     * @param  array<int, array<string, mixed>>  $launchCosts
     * @param  array<int, array<string, mixed>>  $monthlyFixedCosts
     * @param  array<int, array<string, mixed>>  $revenueForecast
     * @param  array<int, array<string, mixed>>  $fundingSources
     * @param  array<int, array<string, mixed>>  $futureCosts
     * @param  array<int, array<string, mixed>>  $fundingScenarios
     * @param  array<string, mixed>  $assumptions
     * @return array<string, mixed>
     */
    public function compute(
        array $launchCosts,
        array $monthlyFixedCosts,
        array $revenueForecast,
        array $fundingSources,
        ?int $expectedRunwayMonths,
        int $forecastYears = self::DEFAULT_FORECAST_YEARS,
        array $assumptions = [],
        array $futureCosts = [],
        array $fundingScenarios = [],
        ?float $companyTaxRatePercent = null,
        ?float $defaultCostInflationPercent = null,
    ): array {
        $forecastYears = in_array($forecastYears, self::SUPPORTED_FORECAST_YEARS, true) ? $forecastYears : self::DEFAULT_FORECAST_YEARS;
        $launchRows = $this->normaliseRows($launchCosts);
        $fixedRows = $this->normaliseRows($monthlyFixedCosts);
        $revenueRows = $this->normaliseRows($revenueForecast);
        $fundingRows = $this->normaliseRows($fundingSources);
        $futureRows = $this->normaliseFutureCosts($futureCosts);
        $scenarioRows = $this->normaliseFundingScenarios($fundingScenarios);
        $normalisedAssumptions = $this->normaliseAssumptions($assumptions, $companyTaxRatePercent, $defaultCostInflationPercent);

        $baseScenario = [
            'key' => 'base',
            'name' => 'Base case',
            'type' => 'base',
            'amount' => 0.0,
            'year' => 1,
            'interest_rate_percent' => 0.0,
            'term_years' => 0,
            'interest_only_months' => 0,
            'confidence' => 'estimate',
        ];
        $scenarioOutputs = collect([$baseScenario, ...$scenarioRows, ...$this->automaticSensitivityScenarios()])
            ->map(fn (array $scenario): array => $this->computeScenario(
                scenario: $scenario,
                launchRows: $launchRows,
                fixedRows: $fixedRows,
                revenueRows: $revenueRows,
                fundingRows: $fundingRows,
                futureRows: $futureRows,
                assumptions: $normalisedAssumptions,
                forecastYears: $forecastYears,
                expectedRunwayMonths: $expectedRunwayMonths,
            ))
            ->values()
            ->all();

        $base = $scenarioOutputs[0];
        $populatedInputs = [
            'launch_costs' => count($launchRows),
            'monthly_fixed_costs' => count($fixedRows),
            'future_costs' => count($futureRows),
            'revenue_forecast' => count($revenueRows),
            'funding_sources' => count($fundingRows),
            'funding_scenarios' => count($scenarioRows),
            'expected_runway_months' => $expectedRunwayMonths === null ? 0 : 1,
            'assumptions' => count($normalisedAssumptions['provided_fields']),
        ];

        $yearTwoRevenueBridge = $this->yearTwoRevenueBridge(
            (array) ($base['monthly_detail'] ?? []),
            $normalisedAssumptions,
        );

        return [
            'forecast_years' => $forecastYears,
            'assumptions' => $normalisedAssumptions,
            'scenarios' => $scenarioOutputs,
            'base_scenario' => $base,
            'annual_totals' => $base['annual_totals'],
            'monthly_detail' => $base['monthly_detail'],
            'year_two_revenue_bridge' => $yearTwoRevenueBridge,
            'total_launch_costs' => $base['summary']['total_launch_costs'],
            'monthly_fixed_costs' => $base['summary']['year_one_monthly_fixed_costs'],
            'total_funding' => $base['summary']['total_funding'],
            'opening_cash_balance' => $base['summary']['opening_cash_balance'],
            'available_after_launch' => $base['summary']['available_after_launch'],
            'runway_months' => $base['summary']['runway_months'],
            'runway_open_ended' => $base['summary']['runway_open_ended'],
            'break_even_month' => $base['summary']['break_even_month'],
            'break_even_year' => $base['summary']['break_even_year'],
            'first_profitable_year' => $base['summary']['first_profitable_year'],
            'cash_flow_positive_year' => $base['summary']['cash_flow_positive_year'],
            'break_even_reached' => $base['summary']['break_even_year'] !== null,
            'monthly_series' => array_slice($base['monthly_detail'], 0, self::MONTHS_PER_YEAR),
            'populated_inputs' => $populatedInputs,
            'input_count' => array_sum($populatedInputs),
            'explanations' => $this->metricExplanations(),
            'missing_assumptions' => $normalisedAssumptions['missing_fields'],
            'input_quality' => $this->inputQuality($fixedRows, $revenueRows, $normalisedAssumptions),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function normaliseRows(array $rows): array
    {
        return collect($rows)
            ->map(function (array $row): array {
                $label = trim((string) ($row['label'] ?? $row['name'] ?? ''));
                $amount = $this->number($row['amount'] ?? $row['value'] ?? 0);
                $quantity = max(1.0, $this->number($row['quantity'] ?? 1));
                $month = $this->month($row['month'] ?? 1);
                $growthPercent = $this->signedPercent($row['growth_percent'] ?? $row['monthly_growth_percent'] ?? 0);
                $variableCostPercent = $this->number($row['variable_cost_percent'] ?? 0);
                $unitCost = $this->number($row['unit_cost'] ?? 0);
                $grossProfitPercent = $this->nullablePercent($row['gross_profit_percent'] ?? $row['gp_percent'] ?? null);
                $confidence = $this->confidence($row['confidence'] ?? null);
                $cadence = $this->cadence($row['cadence'] ?? null);
                $growthCadence = $this->growthCadence($row['growth_cadence'] ?? null);
                $monthlyCapacityUnits = $this->nullableNonNegativeNumber($row['monthly_capacity_units'] ?? null);
                $founderCapacityUnits = $this->nullableNonNegativeNumber($row['founder_capacity_units'] ?? null);
                $contractorUnitCost = $this->number($row['contractor_unit_cost'] ?? 0);

                if ($grossProfitPercent !== null) {
                    $variableCostPercent = max(0.0, min(100.0, 100.0 - $grossProfitPercent));
                } elseif ($unitCost > 0 && $amount > 0) {
                    $variableCostPercent = max(0.0, min(100.0, ($unitCost / $amount) * 100));
                    $grossProfitPercent = 100.0 - $variableCostPercent;
                }

                return [
                    'label' => $label,
                    'amount' => round($amount, 2),
                    'quantity' => round($quantity, 2),
                    'month' => $month,
                    'cadence' => $cadence,
                    'cadence_confirmed' => (bool) ($row['cadence_confirmed'] ?? false),
                    'growth_percent' => round($growthPercent, 2),
                    // Kept for legacy consumers while the UI migrates to the cadence-neutral name.
                    'monthly_growth_percent' => round($growthPercent, 2),
                    'growth_cadence' => $growthCadence,
                    'growth_cadence_confirmed' => (bool) ($row['growth_cadence_confirmed'] ?? false),
                    'monthly_capacity_units' => $monthlyCapacityUnits === null ? null : round($monthlyCapacityUnits, 2),
                    'capacity_confirmed' => (bool) ($row['capacity_confirmed'] ?? false),
                    'founder_capacity_units' => $founderCapacityUnits === null ? null : round(min($founderCapacityUnits, $monthlyCapacityUnits ?? $founderCapacityUnits), 2),
                    'contractor_unit_cost' => round($contractorUnitCost, 2),
                    'contractor_cost_confirmed' => (bool) ($row['contractor_cost_confirmed'] ?? false),
                    'unit_label' => trim((string) ($row['unit_label'] ?? 'units')),
                    'variable_cost_percent' => round($variableCostPercent, 2),
                    'unit_cost' => round($unitCost, 2),
                    'gross_profit_percent' => $grossProfitPercent === null ? null : round($grossProfitPercent, 2),
                    'confidence' => $confidence,
                    'source_type' => $this->sourceType($row['source_type'] ?? null),
                    'source_reference' => $this->sourceReference($row['source_reference'] ?? null),
                    'source_confirmed' => (bool) ($row['source_confirmed'] ?? false),
                ];
            })
            ->filter(fn (array $row): bool => $row['label'] !== '' || $row['amount'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function normaliseFutureCosts(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row): array => [
                'label' => trim((string) ($row['label'] ?? $row['name'] ?? '')),
                'amount' => round($this->number($row['amount'] ?? 0), 2),
                'quantity' => max(1.0, $this->number($row['quantity'] ?? 1)),
                'year' => min(5, max(2, (int) ($row['year'] ?? 2))),
                'recurring' => (bool) ($row['recurring'] ?? false),
                'classification' => ($row['classification'] ?? null) === 'capital' ? 'capital' : 'operating',
                'useful_life_years' => min(20, max(1, (int) ($row['useful_life_years'] ?? 3))),
                'confidence' => $this->confidence($row['confidence'] ?? null),
            ])
            ->filter(fn (array $row): bool => $row['label'] !== '' || $row['amount'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function normaliseFundingScenarios(array $rows): array
    {
        return collect($rows)
            ->map(function (array $row, int $index): array {
                $type = in_array($row['type'] ?? '', ['bank_loan', 'investor', 'mixed'], true)
                    ? (string) $row['type']
                    : 'bank_loan';

                $termYears = in_array($type, ['bank_loan', 'mixed'], true)
                    ? min(30, max(1, (int) ($row['term_years'] ?? 1)))
                    : 0;

                return [
                    'key' => 'scenario_'.($index + 1),
                    'name' => trim((string) ($row['name'] ?? $this->scenarioName($type, $index + 1))),
                    'type' => $type,
                    'amount' => round($this->number($row['amount'] ?? 0), 2),
                    'year' => min(5, max(1, (int) ($row['year'] ?? 1))),
                    'interest_rate_percent' => round($this->number($row['interest_rate_percent'] ?? 0), 2),
                    'term_years' => $termYears,
                    'interest_only_months' => in_array($type, ['bank_loan', 'mixed'], true)
                        ? min(max(0, ($termYears * self::MONTHS_PER_YEAR) - 1), max(0, (int) ($row['interest_only_months'] ?? 0)))
                        : 0,
                    'investor_equity_percent' => round($this->number($row['investor_equity_percent'] ?? 0), 2),
                    'confidence' => $this->confidence($row['confidence'] ?? null),
                ];
            })
            ->filter(fn (array $row): bool => $row['name'] !== '' && $row['amount'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $assumptions
     * @return array<string, mixed>
     */
    public function normaliseAssumptions(array $assumptions, ?float $companyTaxRatePercent, ?float $defaultCostInflationPercent): array
    {
        $fields = [
            'revenue_growth_percent' => 'Annual revenue growth %',
            'cost_inflation_percent' => 'Cost inflation / CPI %',
            'target_gross_profit_percent' => 'Target GP %',
            'target_net_profit_before_tax_percent' => 'Target net profit before tax %',
            'target_net_profit_after_tax_percent' => 'Target net profit after tax %',
        ];
        $missing = [];
        $provided = [];
        $normalised = [];
        $openingCashRaw = $assumptions['opening_cash_balance'] ?? null;
        $openingCash = 0.0;

        foreach ($fields as $key => $label) {
            $raw = $assumptions[$key] ?? null;
            if (($raw === null || $raw === '') && $key === 'cost_inflation_percent' && $defaultCostInflationPercent !== null) {
                $raw = $defaultCostInflationPercent;
            }

            if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                $missing[] = $key;
                $normalised[$key] = 0.0;

                continue;
            }

            $provided[] = $key;
            $normalised[$key] = in_array($key, ['revenue_growth_percent', 'cost_inflation_percent'], true)
                ? round($this->signedPercent($raw), 2)
                : round($this->number($raw), 2);
        }

        if ($openingCashRaw !== null && $openingCashRaw !== '' && is_numeric($openingCashRaw)) {
            $provided[] = 'opening_cash_balance';
            $openingCash = max(0.0, $this->number($openingCashRaw));
        }
        $debtorDays = $this->nonNegativeDays($assumptions['debtor_days'] ?? $assumptions['accounts_receivable_days'] ?? null);
        $creditorDays = $this->nonNegativeDays($assumptions['creditor_days'] ?? $assumptions['accounts_payable_days'] ?? null);

        if ($debtorDays !== null) {
            $provided[] = 'debtor_days';
        }

        if ($creditorDays !== null) {
            $provided[] = 'creditor_days';
        }

        $taxConfigured = $companyTaxRatePercent !== null;
        $yearTwoRevenueBasis = ($assumptions['year_two_revenue_basis'] ?? null) === 'year_one_average'
            ? 'year_one_average'
            : 'exit_run_rate';
        if (array_key_exists('year_two_revenue_basis', $assumptions)) {
            $provided[] = 'year_two_revenue_basis';
        }
        $forecastStartMonth = is_string($assumptions['forecast_start_month'] ?? null)
            && preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', (string) $assumptions['forecast_start_month']) === 1
            ? (string) $assumptions['forecast_start_month']
            : null;
        if ($forecastStartMonth !== null) {
            $provided[] = 'forecast_start_month';
        }

        $fundingPosition = in_array($assumptions['funding_position'] ?? null, ['self_funded', 'external_funding'], true)
            ? (string) $assumptions['funding_position']
            : 'undecided';

        return [
            ...$normalised,
            'year_two_revenue_basis' => $yearTwoRevenueBasis,
            'forecast_start_month' => $forecastStartMonth,
            'opening_cash_balance' => round($openingCash, 2),
            'debtor_days' => $debtorDays ?? 0,
            'creditor_days' => $creditorDays ?? 0,
            'opening_cash_verified' => (bool) ($assumptions['opening_cash_verified'] ?? false),
            'working_capital_verified' => (bool) ($assumptions['working_capital_verified'] ?? false),
            'forecast_start_confirmed' => (bool) ($assumptions['forecast_start_confirmed'] ?? false),
            'funding_position' => $fundingPosition,
            'funding_position_confirmed' => (bool) ($assumptions['funding_position_confirmed'] ?? false),
            'funding_request_purpose' => $this->sourceReference($assumptions['funding_request_purpose'] ?? null),
            'working_capital_timing' => [
                'debtor_days' => $debtorDays ?? 0,
                'creditor_days' => $creditorDays ?? 0,
                'debtor_lag_months' => $this->lagMonthsFromDays($debtorDays ?? 0),
                'creditor_lag_months' => $this->lagMonthsFromDays($creditorDays ?? 0),
                'basis' => 'Optional debtor and creditor days convert forecast profit timing into cash timing. Defaults are zero days when not supplied.',
            ],
            'company_tax_rate_percent' => $taxConfigured ? round(max(0.0, $companyTaxRatePercent), 2) : 0.0,
            'company_tax_configured' => $taxConfigured,
            'gst_exclusive' => true,
            'provided_fields' => $provided,
            'missing_fields' => $missing,
            'field_labels' => [
                ...$fields,
                'year_two_revenue_basis' => 'Revenue basis after year 1',
                'forecast_start_month' => 'Forecast start month',
                'opening_cash_balance' => 'Opening cash balance',
                'debtor_days' => 'Debtor days',
                'creditor_days' => 'Creditor days',
                'opening_cash_verified' => 'Opening cash verification',
                'working_capital_verified' => 'Working-capital timing verification',
                'forecast_start_confirmed' => 'Forecast start-month confirmation',
                'funding_position' => 'Funding position',
                'funding_position_confirmed' => 'Funding-position confirmation',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<int, array<string, mixed>>  $launchRows
     * @param  array<int, array<string, mixed>>  $fixedRows
     * @param  array<int, array<string, mixed>>  $revenueRows
     * @param  array<int, array<string, mixed>>  $fundingRows
     * @param  array<int, array<string, mixed>>  $futureRows
     * @param  array<string, mixed>  $assumptions
     * @return array<string, mixed>
     */
    private function computeScenario(
        array $scenario,
        array $launchRows,
        array $fixedRows,
        array $revenueRows,
        array $fundingRows,
        array $futureRows,
        array $assumptions,
        int $forecastYears,
        ?int $expectedRunwayMonths,
    ): array {
        $monthCount = $forecastYears * self::MONTHS_PER_YEAR;
        $monthly = [];
        $annual = [];
        $openingCashBalance = (float) ($assumptions['opening_cash_balance'] ?? 0.0);
        $cumulativeCash = $openingCashBalance;
        $loan = $this->loanState($scenario);
        $runwayMonths = null;
        $breakEvenMonth = null;
        $firstProfitYear = null;
        $cashPositiveYear = null;
        // A tax loss is a running balance. Keying it by forecast year quietly
        // discarded a Year 1 loss when the Year 2 forecast began.
        $taxLossCarryForward = 0.0;
        $revenueMultiplier = $this->scenarioMultiplier($scenario, 'revenue_multiplier');
        $costMultiplier = $this->scenarioMultiplier($scenario, 'cost_multiplier');
        $debtorLagMonths = $this->lagMonthsFromDays((int) ($assumptions['debtor_days'] ?? 0));
        $creditorLagMonths = $this->lagMonthsFromDays((int) ($assumptions['creditor_days'] ?? 0));

        for ($month = 1; $month <= $monthCount; $month++) {
            $year = (int) ceil($month / self::MONTHS_PER_YEAR);
            $monthInYear = (($month - 1) % self::MONTHS_PER_YEAR) + 1;
            $revenue = $this->revenueForMonth($revenueRows, $month, $assumptions) * $revenueMultiplier;
            $contractorDeliveryCosts = $this->contractorDeliveryCostsForMonth($revenueRows, $month, $assumptions, $revenueMultiplier) * $costMultiplier;
            $variableCosts = ($this->variableCostsForMonth($revenueRows, $month, $assumptions) * $revenueMultiplier * $costMultiplier) + $contractorDeliveryCosts;
            $cashCollected = $this->cashCollectedForMonth($revenueRows, $month, $assumptions, $debtorLagMonths) * $revenueMultiplier;
            $variableCostsPaid = ($this->variableCostsPaidForMonth($revenueRows, $month, $assumptions, $creditorLagMonths) * $revenueMultiplier * $costMultiplier)
                + ($this->contractorDeliveryCostsForMonth($revenueRows, $month - $creditorLagMonths, $assumptions, $revenueMultiplier) * $costMultiplier);
            $workingCapitalTimingAdjustment = ($cashCollected - $revenue) + ($variableCosts - $variableCostsPaid);
            $fixedCosts = $this->fixedCostsForMonth($fixedRows, $month, $assumptions) * $costMultiplier;
            $futureOperatingCosts = $this->futureOperatingCostsForMonth($futureRows, $year, $monthInYear) * $costMultiplier;
            $capitalExpenditure = $this->capitalExpenditureForMonth($futureRows, $month) * $costMultiplier;
            $depreciation = $this->depreciationForMonth($futureRows, $month) * $costMultiplier;
            $launchCosts = $this->rowsForMonth($launchRows, $month) * $costMultiplier;
            $fundingInflow = $this->fundingForMonth($fundingRows, $month) + $this->scenarioFundingForMonth($scenario, $month);
            $loanPayment = $this->loanPaymentForMonth($loan, $scenario, $month);
            $grossProfit = $revenue - $variableCosts;
            $operatingProfit = $grossProfit - $fixedCosts - $futureOperatingCosts - $depreciation;
            $netProfitBeforeTax = $operatingProfit - $loanPayment['interest'];
            $taxLossUsed = 0.0;
            $taxableProfit = $netProfitBeforeTax;
            $tax = 0.0;
            if ((bool) $assumptions['company_tax_configured']) {
                if ($netProfitBeforeTax > 0 && $taxLossCarryForward < 0) {
                    $taxLossUsed = min($netProfitBeforeTax, abs($taxLossCarryForward));
                }
                $taxableProfit = $netProfitBeforeTax - $taxLossUsed;
                if ($taxableProfit > 0) {
                    $tax = $taxableProfit * (((float) $assumptions['company_tax_rate_percent']) / 100);
                    $taxLossCarryForward = 0.0;
                } else {
                    $taxLossCarryForward = min(0.0, $taxLossCarryForward + $netProfitBeforeTax);
                }
            }
            $netProfitAfterTax = $netProfitBeforeTax - $tax;
            $netCashFlow = $netProfitAfterTax + $depreciation + $fundingInflow - $launchCosts - $capitalExpenditure - $loanPayment['principal'] + $workingCapitalTimingAdjustment;
            $cumulativeCash += $netCashFlow;

            if ($breakEvenMonth === null && $netProfitBeforeTax >= 0 && $revenue > 0) {
                $breakEvenMonth = $month;
            }

            if ($runwayMonths === null && $cumulativeCash < 0) {
                $runwayMonths = max(0, $month - 1);
            }

            $monthly[] = [
                'month' => $month,
                'year' => $year,
                'month_in_year' => $monthInYear,
                'revenue' => round($revenue, 2),
                'variable_costs' => round($variableCosts, 2),
                'contractor_delivery_costs' => round($contractorDeliveryCosts, 2),
                'cash_collected' => round($cashCollected, 2),
                'variable_costs_paid' => round($variableCostsPaid, 2),
                'working_capital_timing_adjustment' => round($workingCapitalTimingAdjustment, 2),
                'gross_profit' => round($grossProfit, 2),
                'fixed_costs' => round($fixedCosts + $futureOperatingCosts, 2),
                'depreciation' => round($depreciation, 2),
                'capital_expenditure' => round($capitalExpenditure, 2),
                'interest' => round($loanPayment['interest'], 2),
                'tax' => round($tax, 2),
                'loan_principal' => round($loanPayment['principal'], 2),
                'funding_inflow' => round($fundingInflow, 2),
                'launch_costs' => round($launchCosts, 2),
                'net_profit_before_tax' => round($netProfitBeforeTax, 2),
                'taxable_profit_after_loss_offset' => round($taxableProfit, 2),
                'tax_loss_used' => round($taxLossUsed, 2),
                'tax_loss_carried_forward' => round($taxLossCarryForward, 2),
                'net_profit_after_tax' => round($netProfitAfterTax, 2),
                'net_cash_flow' => round($netCashFlow, 2),
                'cumulative_cash' => round($cumulativeCash, 2),
            ];
        }

        foreach (range(1, $forecastYears) as $year) {
            $yearRows = collect($monthly)->where('year', $year);
            $row = [
                'year' => $year,
                'revenue' => round((float) $yearRows->sum('revenue'), 2),
                'variable_costs' => round((float) $yearRows->sum('variable_costs'), 2),
                'contractor_delivery_costs' => round((float) $yearRows->sum('contractor_delivery_costs'), 2),
                'cash_collected' => round((float) $yearRows->sum('cash_collected'), 2),
                'variable_costs_paid' => round((float) $yearRows->sum('variable_costs_paid'), 2),
                'working_capital_timing_adjustment' => round((float) $yearRows->sum('working_capital_timing_adjustment'), 2),
                'gross_profit' => round((float) $yearRows->sum('gross_profit'), 2),
                'fixed_costs' => round((float) $yearRows->sum('fixed_costs'), 2),
                'depreciation' => round((float) $yearRows->sum('depreciation'), 2),
                'capital_expenditure' => round((float) $yearRows->sum('capital_expenditure'), 2),
                'interest' => round((float) $yearRows->sum('interest'), 2),
                'tax' => round((float) $yearRows->sum('tax'), 2),
                'tax_loss_used' => round((float) $yearRows->sum('tax_loss_used'), 2),
                'tax_loss_carried_forward' => round((float) ($yearRows->last()['tax_loss_carried_forward'] ?? 0), 2),
                'loan_principal' => round((float) $yearRows->sum('loan_principal'), 2),
                'funding_inflow' => round((float) $yearRows->sum('funding_inflow'), 2),
                'launch_costs' => round((float) $yearRows->sum('launch_costs'), 2),
                'net_profit_before_tax' => round((float) $yearRows->sum('net_profit_before_tax'), 2),
                'net_profit_after_tax' => round((float) $yearRows->sum('net_profit_after_tax'), 2),
                'net_cash_flow' => round((float) $yearRows->sum('net_cash_flow'), 2),
                'ending_cash' => round((float) ($yearRows->last()['cumulative_cash'] ?? 0), 2),
            ];
            $row['gross_profit_percent'] = $this->percent($row['gross_profit'], $row['revenue']);
            $row['net_profit_before_tax_percent'] = $this->percent($row['net_profit_before_tax'], $row['revenue']);
            $row['net_profit_after_tax_percent'] = $this->percent($row['net_profit_after_tax'], $row['revenue']);
            $annual[] = $row;

            if ($firstProfitYear === null && $row['net_profit_after_tax'] > 0) {
                $firstProfitYear = $year;
            }

            if ($cashPositiveYear === null && $row['ending_cash'] >= 0 && $row['revenue'] > 0) {
                $cashPositiveYear = $year;
            }
        }

        $breakEvenAnnual = collect($annual)->first(fn (array $row): bool => (float) $row['net_profit_before_tax'] >= 0 && (float) $row['revenue'] > 0);
        $breakEvenYear = is_array($breakEvenAnnual) ? (int) $breakEvenAnnual['year'] : null;
        $lastMonth = end($monthly);
        $hasAnyInput = $this->hasAnyInput($launchRows, $fixedRows, $revenueRows, $fundingRows, $futureRows)
            || $openingCashBalance > 0.0;
        $runwayOpenEnded = $hasAnyInput && $runwayMonths === null && is_array($lastMonth) && (float) $lastMonth['cumulative_cash'] >= 0;
        if ($runwayMonths === null && $hasAnyInput) {
            $runwayMonths = $monthCount;
        }

        return [
            'key' => $scenario['key'],
            'name' => $scenario['name'],
            'type' => $scenario['type'],
            'automatic' => (bool) ($scenario['automatic'] ?? false),
            'equity_sold_percent' => $this->equitySoldPercent($scenario),
            'founder_ownership_percent' => round(100.0 - $this->equitySoldPercent($scenario), 2),
            'sensitivity' => [
                'revenue_multiplier' => $revenueMultiplier,
                'cost_multiplier' => $costMultiplier,
            ],
            'annual_totals' => $annual,
            'monthly_detail' => $monthly,
            'summary' => [
                'total_launch_costs' => round($this->sumRows($launchRows), 2),
                'year_one_monthly_fixed_costs' => round($this->sumMonthlyFixedRows($fixedRows), 2),
                'total_funding' => round($this->sumRows($fundingRows) + $this->scenarioFundingTotal($scenario), 2),
                'opening_cash_balance' => round($openingCashBalance, 2),
                'available_after_launch' => round($openingCashBalance + $this->sumRows($fundingRows) + $this->scenarioFundingTotal($scenario) - $this->sumRows($launchRows), 2),
                'runway_months' => $runwayMonths,
                'runway_open_ended' => $runwayOpenEnded,
                'break_even_month' => $breakEvenMonth,
                'break_even_year' => $breakEvenYear,
                'first_profitable_year' => $firstProfitYear,
                'cash_flow_positive_year' => $cashPositiveYear,
                'expected_runway_months' => $expectedRunwayMonths,
                'equity_sold_percent' => $this->equitySoldPercent($scenario),
                'founder_ownership_percent' => round(100.0 - $this->equitySoldPercent($scenario), 2),
                'working_capital_timing' => [
                    'debtor_days' => (int) ($assumptions['debtor_days'] ?? 0),
                    'creditor_days' => (int) ($assumptions['creditor_days'] ?? 0),
                    'debtor_lag_months' => $debtorLagMonths,
                    'creditor_lag_months' => $creditorLagMonths,
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function revenueForMonth(array $rows, int $month, array $assumptions): float
    {
        return array_reduce(
            $rows,
            fn (float $total, array $row): float => $total + $this->revenueForRow($row, $month, $assumptions),
            0.0,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $assumptions
     */
    private function revenueForRow(array $row, int $month, array $assumptions): float
    {
        if ((int) $row['month'] > $month) {
            return 0.0;
        }

        $year = (int) ceil($month / self::MONTHS_PER_YEAR);
        if ($year === 1) {
            $elapsed = max(0, $month - (int) $row['month']);
            $revenue = $this->rowBaseRevenue($row) * $this->rowGrowthFactor($row, $elapsed);

            return $this->capRevenueToCapacity($row, $revenue);
        }

        if ((int) $row['month'] > self::MONTHS_PER_YEAR) {
            return 0.0;
        }

        $revenue = ($assumptions['year_two_revenue_basis'] ?? 'exit_run_rate') === 'year_one_average'
            ? $this->yearOneAverageRevenue($row) * $this->growthFactor((float) $assumptions['revenue_growth_percent'], $year - 1)
            : $this->yearOneExitRunRate($row) * $this->annualGrowthForMonths(
                (float) $assumptions['revenue_growth_percent'],
                $month - self::MONTHS_PER_YEAR,
            );

        return $this->capRevenueToCapacity($row, $revenue);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function variableCostsForMonth(array $rows, int $month, array $assumptions): float
    {
        return array_reduce($rows, function (float $total, array $row) use ($month, $assumptions): float {
            $revenue = $this->revenueForMonth([$row], $month, $assumptions);
            $year = (int) ceil($month / self::MONTHS_PER_YEAR);
            $costInflation = $this->growthFactor((float) $assumptions['cost_inflation_percent'], 1);
            $revenueGrowth = $this->growthFactor((float) $assumptions['revenue_growth_percent'], 1);
            $ratioAdjustment = $year > 1 && $revenueGrowth > 0
                ? ($costInflation ** ($year - 1)) / ($revenueGrowth ** ($year - 1))
                : 1;
            $percent = min(100.0, ((float) $row['variable_cost_percent']) * $ratioAdjustment);

            return $total + ($revenue * ($percent / 100));
        }, 0.0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $assumptions
     */
    private function contractorDeliveryCostsForMonth(array $rows, int $month, array $assumptions, float $revenueMultiplier): float
    {
        if ($month < 1) {
            return 0.0;
        }

        return array_reduce($rows, function (float $total, array $row) use ($month, $assumptions, $revenueMultiplier): float {
            $founderCapacity = $row['founder_capacity_units'] ?? null;
            $totalCapacity = $row['monthly_capacity_units'] ?? null;
            $unitPrice = (float) ($row['amount'] ?? 0);
            $contractorUnitCost = (float) ($row['contractor_unit_cost'] ?? 0);

            if (! is_numeric($founderCapacity) || ! is_numeric($totalCapacity) || $unitPrice <= 0 || $contractorUnitCost <= 0) {
                return $total;
            }

            $revenue = $this->revenueForRow($row, $month, $assumptions) * $revenueMultiplier;
            $unitsRequired = $revenue / $unitPrice;
            $contractorUnits = max(0.0, min($unitsRequired, (float) $totalCapacity) - (float) $founderCapacity);

            return $total + ($contractorUnits * $contractorUnitCost);
        }, 0.0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function cashCollectedForMonth(array $rows, int $month, array $assumptions, int $debtorLagMonths): float
    {
        if ($debtorLagMonths <= 0) {
            return $this->revenueForMonth($rows, $month, $assumptions);
        }

        $sourceMonth = $month - $debtorLagMonths;

        return $sourceMonth > 0 ? $this->revenueForMonth($rows, $sourceMonth, $assumptions) : 0.0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function variableCostsPaidForMonth(array $rows, int $month, array $assumptions, int $creditorLagMonths): float
    {
        if ($creditorLagMonths <= 0) {
            return $this->variableCostsForMonth($rows, $month, $assumptions);
        }

        $sourceMonth = $month - $creditorLagMonths;

        return $sourceMonth > 0 ? $this->variableCostsForMonth($rows, $sourceMonth, $assumptions) : 0.0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function fixedCostsForMonth(array $rows, int $month, array $assumptions): float
    {
        $year = (int) ceil($month / self::MONTHS_PER_YEAR);

        return array_reduce($rows, function (float $total, array $row) use ($month, $year, $assumptions): float {
            if ((int) $row['month'] > $month) {
                return $total;
            }

            $amount = $this->monthlyFixedAmount($row);

            return $total + ($amount * $this->growthFactor((float) $assumptions['cost_inflation_percent'], $year - 1));
        }, 0.0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function futureOperatingCostsForMonth(array $rows, int $year, int $monthInYear): float
    {
        return array_reduce($rows, function (float $total, array $row) use ($year, $monthInYear): float {
            if (($row['classification'] ?? 'operating') === 'capital') {
                return $total;
            }

            $rowYear = (int) $row['year'];
            $amount = ((float) $row['amount']) * ((float) $row['quantity']);

            if ((bool) $row['recurring']) {
                return $year >= $rowYear ? $total + $amount : $total;
            }

            return $year === $rowYear && $monthInYear === 1 ? $total + $amount : $total;
        }, 0.0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function capitalExpenditureForMonth(array $rows, int $month): float
    {
        return array_reduce($rows, function (float $total, array $row) use ($month): float {
            if (($row['classification'] ?? 'operating') !== 'capital') {
                return $total;
            }

            $purchaseMonth = (((int) $row['year']) - 1) * self::MONTHS_PER_YEAR + 1;

            return $month === $purchaseMonth
                ? $total + (((float) $row['amount']) * ((float) $row['quantity']))
                : $total;
        }, 0.0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function depreciationForMonth(array $rows, int $month): float
    {
        return array_reduce($rows, function (float $total, array $row) use ($month): float {
            if (($row['classification'] ?? 'operating') !== 'capital') {
                return $total;
            }

            $purchaseMonth = (((int) $row['year']) - 1) * self::MONTHS_PER_YEAR + 1;
            $usefulLifeMonths = max(1, ((int) $row['useful_life_years']) * self::MONTHS_PER_YEAR);
            if ($month < $purchaseMonth || $month >= $purchaseMonth + $usefulLifeMonths) {
                return $total;
            }

            return $total + ((((float) $row['amount']) * ((float) $row['quantity'])) / $usefulLifeMonths);
        }, 0.0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function fundingForMonth(array $rows, int $month): float
    {
        return array_reduce($rows, function (float $total, array $row) use ($month): float {
            return (int) $row['month'] === $month
                ? $total + (((float) $row['amount']) * ((float) $row['quantity']))
                : $total;
        }, 0.0);
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    private function scenarioFundingForMonth(array $scenario, int $month): float
    {
        $startMonth = (((int) $scenario['year']) - 1) * self::MONTHS_PER_YEAR + 1;

        return $month === $startMonth ? $this->scenarioFundingTotal($scenario) : 0.0;
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    private function scenarioFundingTotal(array $scenario): float
    {
        return $scenario['type'] === 'base' ? 0.0 : (float) $scenario['amount'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function automaticSensitivityScenarios(): array
    {
        return [
            [
                'key' => 'revenue_downside',
                'name' => 'Revenue downside',
                'type' => 'sensitivity',
                'amount' => 0.0,
                'year' => 1,
                'interest_rate_percent' => 0.0,
                'term_years' => 0,
                'interest_only_months' => 0,
                'investor_equity_percent' => 0.0,
                'revenue_multiplier' => 0.8,
                'cost_multiplier' => 1.0,
                'automatic' => true,
                'confidence' => 'estimate',
            ],
            [
                'key' => 'cost_upside',
                'name' => 'Cost upside',
                'type' => 'sensitivity',
                'amount' => 0.0,
                'year' => 1,
                'interest_rate_percent' => 0.0,
                'term_years' => 0,
                'interest_only_months' => 0,
                'investor_equity_percent' => 0.0,
                'revenue_multiplier' => 1.0,
                'cost_multiplier' => 1.1,
                'automatic' => true,
                'confidence' => 'estimate',
            ],
            [
                'key' => 'combined_downside',
                'name' => 'Combined downside',
                'type' => 'sensitivity',
                'amount' => 0.0,
                'year' => 1,
                'interest_rate_percent' => 0.0,
                'term_years' => 0,
                'interest_only_months' => 0,
                'investor_equity_percent' => 0.0,
                'revenue_multiplier' => 0.8,
                'cost_multiplier' => 1.1,
                'automatic' => true,
                'confidence' => 'estimate',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    private function scenarioMultiplier(array $scenario, string $key): float
    {
        $value = $scenario[$key] ?? 1.0;

        return is_numeric($value) ? max(0.0, (float) $value) : 1.0;
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    private function equitySoldPercent(array $scenario): float
    {
        if (! in_array($scenario['type'] ?? '', ['investor', 'mixed'], true)) {
            return 0.0;
        }

        return round(max(0.0, min(100.0, (float) ($scenario['investor_equity_percent'] ?? 0))), 2);
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    private function loanState(array $scenario): array
    {
        return [
            'balance' => in_array($scenario['type'], ['bank_loan', 'mixed'], true) ? (float) $scenario['amount'] : 0.0,
            'monthly_payment' => null,
            'started' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return array{interest:float,principal:float}
     */
    private function loanPaymentForMonth(array &$loan, array $scenario, int $month): array
    {
        if (! in_array($scenario['type'], ['bank_loan', 'mixed'], true) || $loan['balance'] <= 0) {
            return ['interest' => 0.0, 'principal' => 0.0];
        }

        $startMonth = (((int) $scenario['year']) - 1) * self::MONTHS_PER_YEAR + 1;
        if ($month < $startMonth) {
            return ['interest' => 0.0, 'principal' => 0.0];
        }

        $elapsed = $month - $startMonth;
        $monthlyRate = (((float) $scenario['interest_rate_percent']) / 100) / self::MONTHS_PER_YEAR;
        $interest = (float) $loan['balance'] * $monthlyRate;
        $principal = 0.0;

        if ($elapsed >= (int) $scenario['interest_only_months']) {
            if ($loan['monthly_payment'] === null) {
                $remainingMonths = max(1, ((int) $scenario['term_years'] * self::MONTHS_PER_YEAR) - (int) $scenario['interest_only_months']);
                $loan['monthly_payment'] = $monthlyRate > 0
                    ? ((float) $loan['balance'] * $monthlyRate) / (1 - ((1 + $monthlyRate) ** (-$remainingMonths)))
                    : ((float) $loan['balance'] / $remainingMonths);
            }

            $principal = min((float) $loan['balance'], max(0.0, (float) $loan['monthly_payment'] - $interest));
            $loan['balance'] = max(0.0, (float) $loan['balance'] - $principal);
        }

        return ['interest' => $interest, 'principal' => $principal];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function yearOneAverageRevenue(array $row): float
    {
        $values = [];
        for ($month = 1; $month <= self::MONTHS_PER_YEAR; $month++) {
            if ((int) $row['month'] > $month) {
                continue;
            }

            $elapsed = max(0, $month - (int) $row['month']);
            $values[] = $this->capRevenueToCapacity($row, $this->rowBaseRevenue($row) * $this->rowGrowthFactor($row, $elapsed));
        }

        return count($values) > 0 ? array_sum($values) / self::MONTHS_PER_YEAR : 0.0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function yearOneExitRunRate(array $row): float
    {
        $elapsed = max(0, self::MONTHS_PER_YEAR - (int) $row['month']);

        return $this->capRevenueToCapacity($row, $this->rowBaseRevenue($row) * $this->rowGrowthFactor($row, $elapsed));
    }

    /**
     * @param  array<int, array<string, mixed>>  $monthlyRows
     * @param  array<string, mixed>  $assumptions
     * @return array<string, mixed>
     */
    private function yearTwoRevenueBridge(array $monthlyRows, array $assumptions): array
    {
        $monthTwelve = collect($monthlyRows)
            ->first(fn (array $row): bool => (int) ($row['month'] ?? 0) === self::MONTHS_PER_YEAR);
        $monthThirteen = collect($monthlyRows)
            ->first(fn (array $row): bool => (int) ($row['month'] ?? 0) === self::MONTHS_PER_YEAR + 1);
        $yearOneRows = collect($monthlyRows)
            ->filter(fn (array $row): bool => (int) ($row['year'] ?? 0) === 1);

        $monthTwelveRevenue = is_array($monthTwelve) && is_numeric($monthTwelve['revenue'] ?? null)
            ? (float) $monthTwelve['revenue']
            : null;
        $monthThirteenRevenue = is_array($monthThirteen) && is_numeric($monthThirteen['revenue'] ?? null)
            ? (float) $monthThirteen['revenue']
            : null;
        $yearOneAverage = $yearOneRows->isNotEmpty()
            ? round(((float) $yearOneRows->sum(fn (array $row): float => (float) ($row['revenue'] ?? 0))) / self::MONTHS_PER_YEAR, 2)
            : null;
        $changeAmount = $monthTwelveRevenue !== null && $monthThirteenRevenue !== null
            ? round($monthThirteenRevenue - $monthTwelveRevenue, 2)
            : null;
        $changePercent = $changeAmount !== null && $monthTwelveRevenue !== null && $monthTwelveRevenue > 0
            ? round(($changeAmount / $monthTwelveRevenue) * 100, 2)
            : null;
        $basis = (string) ($assumptions['year_two_revenue_basis'] ?? 'exit_run_rate');

        return [
            'basis' => $basis,
            'basis_label' => $basis === 'year_one_average'
                ? 'Year 1 average monthly revenue'
                : 'Year 1 exit run-rate',
            'month_12_revenue' => $monthTwelveRevenue === null ? null : round($monthTwelveRevenue, 2),
            'month_13_revenue' => $monthThirteenRevenue === null ? null : round($monthThirteenRevenue, 2),
            'year_one_average_monthly_revenue' => $yearOneAverage,
            'change_amount' => $changeAmount,
            'change_percent' => $changePercent,
            'material_drop' => $monthTwelveRevenue !== null
                && $monthThirteenRevenue !== null
                && $monthTwelveRevenue > 0
                && $monthThirteenRevenue < ($monthTwelveRevenue * 0.8),
            'explanation' => $basis === 'year_one_average'
                ? 'Month 13 is based on the Year 1 average monthly revenue. Use only for an intentional seasonal or averaged forecast.'
                : 'Month 13 carries forward the Year 1 exit run-rate, then applies annual revenue growth smoothly month by month.',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function sumRows(array $rows): float
    {
        return array_reduce(
            $rows,
            fn (float $total, array $row): float => $total + (((float) $row['amount']) * ((float) $row['quantity'])),
            0.0,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function sumMonthlyFixedRows(array $rows): float
    {
        return array_reduce(
            $rows,
            fn (float $total, array $row): float => $total + $this->monthlyFixedAmount($row),
            0.0,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function monthlyFixedAmount(array $row): float
    {
        $amount = ((float) $row['amount']) * ((float) $row['quantity']);

        return match ($row['cadence'] ?? 'monthly') {
            'weekly' => $amount * (52 / self::MONTHS_PER_YEAR),
            'fortnightly' => $amount * (26 / self::MONTHS_PER_YEAR),
            'quarterly' => $amount / 3,
            'annual' => $amount / self::MONTHS_PER_YEAR,
            default => $amount,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowBaseRevenue(array $row): float
    {
        return ((float) $row['amount']) * ((float) $row['quantity']);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowGrowthFactor(array $row, int $elapsedMonths): float
    {
        $growthPercent = (float) ($row['growth_percent'] ?? $row['monthly_growth_percent'] ?? 0);

        return ($row['growth_cadence'] ?? 'monthly') === 'annual'
            ? $this->annualGrowthForMonths($growthPercent, $elapsedMonths)
            : $this->growthFactor($growthPercent, $elapsedMonths);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function capRevenueToCapacity(array $row, float $revenue): float
    {
        $capacity = $row['monthly_capacity_units'] ?? null;
        if (! is_numeric($capacity) || (float) $capacity <= 0) {
            return $revenue;
        }

        return min($revenue, ((float) $row['amount']) * (float) $capacity);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function rowsForMonth(array $rows, int $month): float
    {
        return array_reduce(
            $rows,
            fn (float $total, array $row): float => (int) $row['month'] === $month
                ? $total + (((float) $row['amount']) * ((float) $row['quantity']))
                : $total,
            0.0,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  ...$groups
     */
    private function hasAnyInput(array ...$groups): bool
    {
        foreach ($groups as $group) {
            if ($group !== []) {
                return true;
            }
        }

        return false;
    }

    private function percent(float $value, float $base): ?float
    {
        if ($base <= 0) {
            return null;
        }

        return round(($value / $base) * 100, 2);
    }

    private function number(mixed $value): float
    {
        return max(0.0, $this->numericValue($value));
    }

    private function nullableNonNegativeNumber(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return max(0.0, (float) $value);
    }

    private function cadence(mixed $value): string
    {
        return in_array($value, ['weekly', 'fortnightly', 'monthly', 'quarterly', 'annual'], true)
            ? (string) $value
            : 'monthly';
    }

    private function growthCadence(mixed $value): string
    {
        return in_array($value, ['monthly', 'annual'], true)
            ? (string) $value
            : 'monthly';
    }

    private function signedPercent(mixed $value): float
    {
        return max(-100.0, min(500.0, $this->numericValue($value)));
    }

    private function nonNegativeDays(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return max(0, min(365, (int) round((float) $value)));
    }

    private function lagMonthsFromDays(int $days): int
    {
        return $days <= 0 ? 0 : (int) ceil($days / 30);
    }

    private function numericValue(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $cleaned = preg_replace('/[^0-9.\-]/', '', $value);

            return is_numeric($cleaned) ? (float) $cleaned : 0.0;
        }

        return 0.0;
    }

    private function growthFactor(float $percent, int $periods): float
    {
        if ($periods <= 0) {
            return 1.0;
        }

        $factor = 1 + ($percent / 100);

        return $factor <= 0 ? 0.0 : $factor ** $periods;
    }

    private function annualGrowthForMonths(float $annualPercent, int $months): float
    {
        if ($months <= 0) {
            return 1.0;
        }

        $factor = 1 + ($annualPercent / 100);

        return $factor <= 0 ? 0.0 : $factor ** ($months / self::MONTHS_PER_YEAR);
    }

    private function nullablePercent(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return min(100.0, max(0.0, (float) $value));
    }

    private function month(mixed $value): int
    {
        $month = is_numeric($value) ? (int) $value : 1;

        return max(1, $month);
    }

    private function confidence(mixed $value): string
    {
        return in_array($value, ['known', 'estimate', 'guess'], true)
            ? (string) $value
            : 'estimate';
    }

    private function scenarioName(string $type, int $index): string
    {
        return match ($type) {
            'investor' => 'Investor scenario '.$index,
            'mixed' => 'Mixed funding scenario '.$index,
            default => 'Bank loan scenario '.$index,
        };
    }

    /**
     * @return array<string, string>
     */
    private function metricExplanations(): array
    {
        return [
            'gross_profit_percent' => 'Gross profit percentage shows how much is left from sales after direct product or delivery costs. A higher GP% usually gives the business more room to pay overheads.',
            'net_profit_before_tax_percent' => 'Net profit before tax percentage shows profit after operating costs and interest, before company tax. This is the break-even measure used in this budget.',
            'net_profit_after_tax_percent' => 'Net profit after tax percentage shows the profit left after estimated company tax. This is closer to what the business keeps.',
            'break_even_year' => 'Break-even year is the first forecast year where net profit before tax is zero or positive.',
            'first_profitable_year' => 'First profitable year is the first forecast year where net profit after tax is positive.',
            'cash_flow_positive_year' => 'Cash-flow-positive year is the first year where cumulative cash becomes zero or positive after startup losses and funding movements.',
            'year_two_revenue_basis' => 'By default, month 13 carries forward the Year 1 exit run-rate and applies annual revenue growth smoothly month by month. Choose the Year 1 average only when it is a deliberate seasonal or averaging assumption.',
            'tax_simplification' => 'Company tax is estimated month by month after carrying losses forward through the forecast. This remains an indicative planning estimate, not a filed tax calculation.',
            'downside_growth' => 'Revenue growth and cost/CPI assumptions can be negative down to -100%, so downside and deflation cases are modelled instead of silently flattened to zero growth.',
            'automatic_scenarios' => 'Automatic sensitivity scenarios compare base case against revenue downside, cost upside, and combined downside cases.',
            'investor_equity' => 'Investor and mixed funding scenarios show the equity sold and remaining founder ownership so funding is not treated like free cash.',
            'gst_exclusive' => 'The budget is GST exclusive. GST collected and paid is excluded from profit and cash movements, so a separate GST provision or cash schedule is still required when payment timing is material.',
            'working_capital_timing' => 'Debtor days delay forecast revenue into cash collected; creditor days delay direct variable-cost payments. If no timing is supplied, the budget assumes same-month cash movement.',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fixedRows
     * @param  array<int, array<string, mixed>>  $revenueRows
     * @param  array<string, mixed>  $assumptions
     * @return array{
     *     unconfirmed_fixed_cost_cadences: array<int, mixed>,
     *     unconfirmed_revenue_growth: array<int, mixed>,
     *     revenue_without_capacity: array<int, mixed>,
     *     revenue_with_unpriced_contractors: array<int, mixed>,
     *     unverified_fixed_cost_sources: array<int, mixed>,
     *     unverified_revenue_sources: array<int, mixed>,
     *     unverified_cash_timing: array<int, string>,
     *     funding_position_unconfirmed: bool,
     *     missing_assumptions: array<int, mixed>
     * }
     */
    private function inputQuality(array $fixedRows, array $revenueRows, array $assumptions): array
    {
        return [
            'unconfirmed_fixed_cost_cadences' => collect($fixedRows)
                ->filter(fn (array $row): bool => ! (bool) ($row['cadence_confirmed'] ?? false))
                ->pluck('label')
                ->filter()
                ->values()
                ->all(),
            'unconfirmed_revenue_growth' => collect($revenueRows)
                ->filter(fn (array $row): bool => (float) ($row['growth_percent'] ?? 0) !== 0.0 && ! (bool) ($row['growth_cadence_confirmed'] ?? false))
                ->pluck('label')
                ->filter()
                ->values()
                ->all(),
            'revenue_without_capacity' => collect($revenueRows)
                ->filter(fn (array $row): bool => (float) ($row['amount'] ?? 0) > 0 && (! is_numeric($row['monthly_capacity_units'] ?? null) || ! (bool) ($row['capacity_confirmed'] ?? false)))
                ->pluck('label')
                ->filter()
                ->values()
                ->all(),
            'revenue_with_unpriced_contractors' => collect($revenueRows)
                ->filter(fn (array $row): bool => is_numeric($row['monthly_capacity_units'] ?? null)
                    && is_numeric($row['founder_capacity_units'] ?? null)
                    && (float) $row['monthly_capacity_units'] > (float) $row['founder_capacity_units']
                    && ((float) ($row['contractor_unit_cost'] ?? 0) <= 0 || ! (bool) ($row['contractor_cost_confirmed'] ?? false)))
                ->pluck('label')
                ->filter()
                ->values()
                ->all(),
            'unverified_fixed_cost_sources' => collect($fixedRows)
                ->filter(fn (array $row): bool => ! $this->hasVerifiedSource($row))
                ->pluck('label')
                ->filter()
                ->values()
                ->all(),
            'unverified_revenue_sources' => collect($revenueRows)
                ->filter(fn (array $row): bool => ! $this->hasVerifiedSource($row))
                ->pluck('label')
                ->filter()
                ->values()
                ->all(),
            'unverified_cash_timing' => collect([
                'opening_cash_balance' => (bool) ($assumptions['opening_cash_verified'] ?? false),
                'debtor_and_creditor_days' => (bool) ($assumptions['working_capital_verified'] ?? false),
                'forecast_start_month' => (bool) ($assumptions['forecast_start_confirmed'] ?? false),
            ])
                ->filter(fn (bool $confirmed): bool => ! $confirmed)
                ->keys()
                ->all(),
            'funding_position_unconfirmed' => ! (bool) ($assumptions['funding_position_confirmed'] ?? false)
                || (string) ($assumptions['funding_position'] ?? 'undecided') === 'undecided',
            'missing_assumptions' => collect([
                ...(array) ($assumptions['missing_fields'] ?? []),
                ...(($assumptions['forecast_start_month'] ?? null) === null ? ['forecast_start_month'] : []),
            ])
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasVerifiedSource(array $row): bool
    {
        return (bool) ($row['source_confirmed'] ?? false)
            && (string) ($row['source_type'] ?? 'unverified') !== 'unverified'
            && trim((string) ($row['source_reference'] ?? '')) !== '';
    }

    private function sourceType(mixed $value): string
    {
        return in_array($value, ['bank_statement', 'xero_ledger', 'supplier_quote', 'signed_contract', 'pipeline_evidence', 'owner_record'], true)
            ? (string) $value
            : 'unverified';
    }

    private function sourceReference(mixed $value): string
    {
        return mb_substr(trim((string) $value), 0, 180);
    }
}
