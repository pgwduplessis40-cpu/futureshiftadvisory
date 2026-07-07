<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

final class BudgetCalculator
{
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
        $scenarioOutputs = collect([$baseScenario, ...$scenarioRows])
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

        return [
            'forecast_years' => $forecastYears,
            'assumptions' => $normalisedAssumptions,
            'scenarios' => $scenarioOutputs,
            'base_scenario' => $base,
            'annual_totals' => $base['annual_totals'],
            'monthly_detail' => $base['monthly_detail'],
            'total_launch_costs' => $base['summary']['total_launch_costs'],
            'monthly_fixed_costs' => $base['summary']['year_one_monthly_fixed_costs'],
            'total_funding' => $base['summary']['total_funding'],
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
                $monthlyGrowthPercent = $this->signedPercent($row['monthly_growth_percent'] ?? $row['growth'] ?? 0);
                $variableCostPercent = $this->number($row['variable_cost_percent'] ?? 0);
                $unitCost = $this->number($row['unit_cost'] ?? 0);
                $grossProfitPercent = $this->nullablePercent($row['gross_profit_percent'] ?? $row['gp_percent'] ?? null);
                $confidence = $this->confidence($row['confidence'] ?? null);

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
                    'monthly_growth_percent' => round($monthlyGrowthPercent, 2),
                    'variable_cost_percent' => round($variableCostPercent, 2),
                    'unit_cost' => round($unitCost, 2),
                    'gross_profit_percent' => $grossProfitPercent === null ? null : round($grossProfitPercent, 2),
                    'confidence' => $confidence,
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

                return [
                    'key' => 'scenario_'.($index + 1),
                    'name' => trim((string) ($row['name'] ?? $this->scenarioName($type, $index + 1))),
                    'type' => $type,
                    'amount' => round($this->number($row['amount'] ?? 0), 2),
                    'year' => min(5, max(1, (int) ($row['year'] ?? 1))),
                    'interest_rate_percent' => round($this->number($row['interest_rate_percent'] ?? 0), 2),
                    'term_years' => min(30, max(0, (int) ($row['term_years'] ?? 0))),
                    'interest_only_months' => min(120, max(0, (int) ($row['interest_only_months'] ?? 0))),
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
            'revenue_growth_percent' => 'Revenue growth %',
            'cost_inflation_percent' => 'Cost inflation / CPI %',
            'target_gross_profit_percent' => 'Target GP %',
            'target_net_profit_before_tax_percent' => 'Target net profit before tax %',
            'target_net_profit_after_tax_percent' => 'Target net profit after tax %',
        ];
        $missing = [];
        $provided = [];
        $normalised = [];

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

        $taxConfigured = $companyTaxRatePercent !== null;

        return [
            ...$normalised,
            'company_tax_rate_percent' => $taxConfigured ? round(max(0.0, $companyTaxRatePercent), 2) : 0.0,
            'company_tax_configured' => $taxConfigured,
            'gst_exclusive' => true,
            'provided_fields' => $provided,
            'missing_fields' => $missing,
            'field_labels' => $fields,
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
        $cumulativeCash = 0.0;
        $loan = $this->loanState($scenario);
        $runwayMonths = null;
        $breakEvenMonth = null;
        $firstProfitYear = null;
        $cashPositiveYear = null;

        for ($month = 1; $month <= $monthCount; $month++) {
            $year = (int) ceil($month / self::MONTHS_PER_YEAR);
            $monthInYear = (($month - 1) % self::MONTHS_PER_YEAR) + 1;
            $revenue = $this->revenueForMonth($revenueRows, $month, $assumptions);
            $variableCosts = $this->variableCostsForMonth($revenueRows, $month, $assumptions);
            $fixedCosts = $this->fixedCostsForMonth($fixedRows, $year, $assumptions);
            $futureCosts = $this->futureCostsForMonth($futureRows, $year, $monthInYear);
            $launchCosts = $this->rowsForMonth($launchRows, $month);
            $fundingInflow = $this->fundingForMonth($fundingRows, $month) + $this->scenarioFundingForMonth($scenario, $month);
            $loanPayment = $this->loanPaymentForMonth($loan, $scenario, $month);
            $grossProfit = $revenue - $variableCosts;
            $operatingProfit = $grossProfit - $fixedCosts - $futureCosts;
            $netProfitBeforeTax = $operatingProfit - $loanPayment['interest'];
            $tax = (bool) $assumptions['company_tax_configured'] && $netProfitBeforeTax > 0
                ? $netProfitBeforeTax * (((float) $assumptions['company_tax_rate_percent']) / 100)
                : 0.0;
            $netProfitAfterTax = $netProfitBeforeTax - $tax;
            $netCashFlow = $netProfitAfterTax + $fundingInflow - $launchCosts - $loanPayment['principal'];
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
                'gross_profit' => round($grossProfit, 2),
                'fixed_costs' => round($fixedCosts + $futureCosts, 2),
                'interest' => round($loanPayment['interest'], 2),
                'tax' => round($tax, 2),
                'loan_principal' => round($loanPayment['principal'], 2),
                'funding_inflow' => round($fundingInflow, 2),
                'launch_costs' => round($launchCosts, 2),
                'net_profit_before_tax' => round($netProfitBeforeTax, 2),
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
                'gross_profit' => round((float) $yearRows->sum('gross_profit'), 2),
                'fixed_costs' => round((float) $yearRows->sum('fixed_costs'), 2),
                'interest' => round((float) $yearRows->sum('interest'), 2),
                'tax' => round((float) $yearRows->sum('tax'), 2),
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
        $hasAnyInput = $this->hasAnyInput($launchRows, $fixedRows, $revenueRows, $fundingRows, $futureRows);
        $runwayOpenEnded = $hasAnyInput && $runwayMonths === null && is_array($lastMonth) && (float) $lastMonth['cumulative_cash'] >= 0;
        if ($runwayMonths === null && $hasAnyInput) {
            $runwayMonths = $monthCount;
        }

        return [
            'key' => $scenario['key'],
            'name' => $scenario['name'],
            'type' => $scenario['type'],
            'annual_totals' => $annual,
            'monthly_detail' => $monthly,
            'summary' => [
                'total_launch_costs' => round($this->sumRows($launchRows), 2),
                'year_one_monthly_fixed_costs' => round($this->sumRows($fixedRows), 2),
                'total_funding' => round($this->sumRows($fundingRows) + $this->scenarioFundingTotal($scenario), 2),
                'available_after_launch' => round($this->sumRows($fundingRows) + $this->scenarioFundingTotal($scenario) - $this->sumRows($launchRows), 2),
                'runway_months' => $runwayMonths,
                'runway_open_ended' => $runwayOpenEnded,
                'break_even_month' => $breakEvenMonth,
                'break_even_year' => $breakEvenYear,
                'first_profitable_year' => $firstProfitYear,
                'cash_flow_positive_year' => $cashPositiveYear,
                'expected_runway_months' => $expectedRunwayMonths,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function revenueForMonth(array $rows, int $month, array $assumptions): float
    {
        return array_reduce($rows, function (float $total, array $row) use ($month, $assumptions): float {
            if ((int) $row['month'] > $month) {
                return $total;
            }

            $year = (int) ceil($month / self::MONTHS_PER_YEAR);
            $monthInYear = (($month - 1) % self::MONTHS_PER_YEAR) + 1;
            $base = ((float) $row['amount']) * ((float) $row['quantity']);

            if ($year === 1) {
                $elapsed = max(0, $month - (int) $row['month']);

                return $total + ($base * $this->growthFactor((float) $row['monthly_growth_percent'], $elapsed));
            }

            if ((int) $row['month'] > self::MONTHS_PER_YEAR) {
                return $total;
            }

            $yearOneAverage = $this->yearOneAverageRevenue($row);

            return $total + ($yearOneAverage * $this->growthFactor((float) $assumptions['revenue_growth_percent'], $year - 1));
        }, 0.0);
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
     */
    private function fixedCostsForMonth(array $rows, int $year, array $assumptions): float
    {
        $base = $this->sumRows($rows);

        return $base * $this->growthFactor((float) $assumptions['cost_inflation_percent'], $year - 1);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function futureCostsForMonth(array $rows, int $year, int $monthInYear): float
    {
        return array_reduce($rows, function (float $total, array $row) use ($year, $monthInYear): float {
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
            $base = ((float) $row['amount']) * ((float) $row['quantity']);
            $values[] = $base * $this->growthFactor((float) $row['monthly_growth_percent'], $elapsed);
        }

        return count($values) > 0 ? array_sum($values) / self::MONTHS_PER_YEAR : 0.0;
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

    private function signedPercent(mixed $value): float
    {
        return max(-100.0, min(500.0, $this->numericValue($value)));
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
            'year_two_revenue_basis' => 'From year 2 onward, monthly revenue uses the average monthly revenue achieved in year 1, then applies annual revenue growth. If year 1 ramps quickly, month 13 can be lower than month 12.',
            'tax_simplification' => 'Company tax is estimated month by month on positive net profit before tax. Earlier monthly losses are not carried forward, so after-tax profit is conservative and indicative.',
            'downside_growth' => 'Revenue growth, monthly revenue growth, and cost/CPI assumptions can be negative down to -100%, so downside and deflation cases are modelled instead of silently flattened to zero growth.',
            'gst_exclusive' => 'The budget is GST exclusive by default, so GST collected and paid is not treated as business income or cost in this pack.',
        ];
    }
}
