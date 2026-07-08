<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Enums\DiscountMethod;
use App\Enums\PvType;
use App\Models\BusinessValuation as BusinessValuationModel;
use App\Models\Client;
use App\Models\FinancialSnapshot;
use App\Models\PvCalculation;
use App\Models\SuccessionPlan;
use App\Models\ValuationMultiple;
use App\Services\Audit\AuditWriter;
use App\Support\Methodology\ProvidesMethodology;
use InvalidArgumentException;

final class BusinessValuation implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['valuation.business'];
    }

    public function __construct(
        private readonly ValuationMultipleProvider $multiples,
        private readonly PvEngine $pv,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function calculate(Client $client, array $options = []): BusinessValuationModel
    {
        $industryCode = strtoupper((string) ($options['industry_code'] ?? 'M6962'));
        $financials = $this->financialInputs($client, $options);
        $discountMethodOption = $options['discount_method'] ?? null;
        $discountMethod = $discountMethodOption instanceof DiscountMethod
            ? $discountMethodOption
            : DiscountMethod::AdvisorConfigured;
        $discountOptions = is_array($options['discount_options'] ?? null)
            ? $options['discount_options']
            : ['rate' => 0.12, 'rationale' => 'Advisor default valuation discount rate.'];

        $sdeRange = $this->multipleRange($industryCode, ValuationMultiple::METRIC_SDE);
        $ebitdaRange = $this->multipleRange($industryCode, ValuationMultiple::METRIC_EBITDA);
        $sdeValue = $this->applyMultiple('sde', $financials['sde'], $sdeRange);
        $ebitdaValue = $this->applyMultiple('ebitda', $financials['ebitda'], $ebitdaRange);
        $pvCalculation = $this->dcfCalculation($client, $financials['cash_flows'], $discountMethod, $discountOptions);
        $terminalGrowthRate = (float) ($options['terminal_growth_rate'] ?? 0.02);
        $dcfValue = $this->dcfRange($pvCalculation, $terminalGrowthRate);
        $adjustments = $this->adjustments($options['adjustments'] ?? []);
        $methodWeights = $this->methodWeights($options['method_weights'] ?? null);
        $methodRationale = $this->methodRationale($methodWeights, $options['method_rationale'] ?? null);
        $enterpriseReconciled = $this->reconcileWeighted([$sdeValue, $ebitdaValue, $dcfValue], $adjustments, $methodWeights);
        $equityBridge = $this->equityBridge($options['equity_bridge'] ?? [], $enterpriseReconciled);
        $reconciled = $equityBridge['equity_range'];
        $dcfSensitivity = $this->dcfSensitivity(
            calculation: $pvCalculation,
            terminalGrowthRate: $terminalGrowthRate,
            discountRates: $options['sensitivity_discount_rates'] ?? null,
            terminalGrowthRates: $options['sensitivity_terminal_growth_rates'] ?? null,
        );
        $successionComparison = $this->successionComparison($client, $pvCalculation, $terminalGrowthRate);
        $valuationDisclosures = array_values(array_filter([
            ...$this->professionalDisclosures($options, $pvCalculation, $terminalGrowthRate),
            ...$financials['disclosures'],
        ]));

        $attributions = array_values(array_merge(
            [[
                'claim' => 'Business valuation scope, basis, purpose, premise, and reliance limitations were recorded with the valuation row.',
                'source_reference' => 'valuation_disclosures:business_valuation_scope',
            ]],
            $financials['attributions'],
            $sdeRange['source_attributions'],
            $ebitdaRange['source_attributions'],
            $pvCalculation->source_attributions,
            $dcfSensitivity['source_attributions'],
        ));

        $valuation = BusinessValuationModel::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $pvCalculation->getKey(),
            'sde_value' => $sdeValue,
            'ebitda_value' => $ebitdaValue,
            'dcf_value' => $dcfValue,
            'method_weights' => $methodWeights,
            'method_rationale' => $methodRationale,
            'reconciled_low' => $reconciled['low'],
            'reconciled_mid' => $reconciled['mid'],
            'reconciled_high' => $reconciled['high'],
            'adjustments' => $adjustments,
            'data_quality_disclaimer' => $financials['data_quality_disclaimer'],
            'valuation_disclosures' => $valuationDisclosures,
            'equity_bridge' => $equityBridge,
            'dcf_sensitivity' => $dcfSensitivity,
            'succession_comparison' => $successionComparison,
            'source_attributions' => $attributions,
            'as_at' => now(),
        ]);

        $this->audit->record('business_valuation.created', subject: $valuation, after: [
            'client_id' => $client->getKey(),
            'reconciled_low' => $valuation->reconciled_low,
            'reconciled_mid' => $valuation->reconciled_mid,
            'reconciled_high' => $valuation->reconciled_high,
        ]);

        return $valuation;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{sde:float, ebitda:float, cash_flows:array<int, float>, data_quality_disclaimer:?string, disclosures:array<int, array<string, mixed>>, attributions:array<int, array{claim:string, source_reference:string}>}
     */
    private function financialInputs(Client $client, array $options): array
    {
        $snapshot = (bool) ($options['force_questionnaire_financials'] ?? false)
            ? null
            : FinancialSnapshot::query()
                ->where('client_id', $client->getKey())
                ->latest('period_end')
                ->latest('pulled_at')
                ->first();

        if ($snapshot instanceof FinancialSnapshot) {
            $netProfit = (float) data_get($snapshot->profit_and_loss, 'net_profit', 0);
            $rawEbitda = data_get($snapshot->metrics, 'ebitda');
            $ebitdaFallbackUsed = ! is_numeric($rawEbitda);
            $ebitda = $ebitdaFallbackUsed ? $netProfit : (float) $rawEbitda;
            $sde = (float) data_get($snapshot->metrics, 'sde', $ebitda + (float) ($options['owner_salary_adjustment'] ?? 0));
            $cashFlow = (float) data_get($snapshot->cash_flow, 'operating_cash_flow', $ebitda);
            $disclosures = $this->snapshotDisclosures($snapshot, $options);

            if ($ebitdaFallbackUsed) {
                $disclosures[] = [
                    'type' => 'ebitda_fallback',
                    'severity' => 'medium',
                    'message' => 'EBITDA was not present in the connected accounting snapshot, so net profit was used as a temporary EBITDA proxy.',
                    'source_reference' => "financial_snapshot:{$snapshot->id}:profit_and_loss.net_profit",
                ];
            }

            return [
                'sde' => $sde,
                'ebitda' => $ebitda,
                'cash_flows' => $this->projectCashFlows($cashFlow, (float) ($options['growth_rate'] ?? 0.03)),
                'data_quality_disclaimer' => $disclosures === []
                    ? null
                    : collect($disclosures)->pluck('message')->implode(' '),
                'disclosures' => $disclosures,
                'attributions' => [
                    [
                        'claim' => 'Business valuation used the latest connected accounting snapshot.',
                        'source_reference' => "financial_snapshot:{$snapshot->id}",
                    ],
                ],
            ];
        }

        $questionnaire = is_array($options['questionnaire_financials'] ?? null)
            ? $options['questionnaire_financials']
            : [];

        $ebitda = (float) ($questionnaire['ebitda'] ?? $questionnaire['maintainable_earnings'] ?? 0);
        $sde = (float) ($questionnaire['sde'] ?? $ebitda);
        $cashFlows = is_array($questionnaire['cash_flows'] ?? null)
            ? array_map('floatval', array_values($questionnaire['cash_flows']))
            : $this->projectCashFlows((float) ($questionnaire['cash_flow'] ?? $ebitda), (float) ($options['growth_rate'] ?? 0.02));

        if ($ebitda <= 0 || $sde <= 0 || $cashFlows === []) {
            throw new InvalidArgumentException('Business valuation requires accounting data or questionnaire financial inputs.');
        }

        return [
            'sde' => $sde,
            'ebitda' => $ebitda,
            'cash_flows' => $cashFlows,
            'data_quality_disclaimer' => 'Valuation used questionnaire financial inputs because no connected accounting snapshot was available.',
            'disclosures' => [[
                'type' => 'questionnaire_financials',
                'severity' => 'medium',
                'message' => 'Valuation used questionnaire financial inputs because no connected accounting snapshot was available.',
                'source_reference' => (string) ($questionnaire['source_reference'] ?? 'questionnaire:financial_inputs'),
            ]],
            'attributions' => [
                [
                    'claim' => 'Business valuation used advisor-entered questionnaire financial inputs.',
                    'source_reference' => (string) ($questionnaire['source_reference'] ?? 'questionnaire:financial_inputs'),
                ],
            ],
        ];
    }

    /**
     * @return array{range:array<string, mixed>, source_attributions:array<int, array{claim:string, source_reference:string}>}
     */
    private function multipleRange(string $industryCode, string $metric): array
    {
        $range = $this->multiples->rangeFor($industryCode, $metric);

        if ($range === null) {
            throw new InvalidArgumentException("No active {$metric} valuation multiple exists for {$industryCode}.");
        }

        return [
            'range' => $range,
            'source_attributions' => [
                [
                    'claim' => strtoupper($metric).' multiple range came from the active valuation multiple feed.',
                    'source_reference' => (string) $range['source_reference'],
                ],
            ],
        ];
    }

    /**
     * @param  array{range:array<string, mixed>, source_attributions:array<int, array{claim:string, source_reference:string}>}  $multiple
     * @return array{method:string, low:float, mid:float, high:float, input:float, multiple:array<string, mixed>}
     */
    private function applyMultiple(string $method, float $input, array $multiple): array
    {
        $range = $multiple['range'];

        return [
            'method' => $method,
            'low' => round($input * (float) $range['multiple_low'], 2),
            'mid' => round($input * (float) $range['multiple_mid'], 2),
            'high' => round($input * (float) $range['multiple_high'], 2),
            'input' => round($input, 2),
            'multiple' => $range,
        ];
    }

    /**
     * @param  array<int, float>  $cashFlows
     * @param  array<string, mixed>  $discountOptions
     */
    private function dcfCalculation(
        Client $client,
        array $cashFlows,
        DiscountMethod $discountMethod,
        array $discountOptions,
    ): PvCalculation {
        return $this->pv->calculate(
            client: $client,
            type: PvType::BusinessValuation,
            discountMethod: $discountMethod,
            cashFlows: $cashFlows,
            discountOptions: $discountOptions,
        );
    }

    /**
     * @return array{method:string, low:float, mid:float, high:float, terminal_value:float, cash_flow_pv:float}
     */
    private function dcfRange(PvCalculation $calculation, float $terminalGrowthRate): array
    {
        $cashFlows = array_column($calculation->inputs['cash_flows'] ?? [], 'amount');
        $terminalCashFlow = (float) end($cashFlows);
        $period = max(1, count($cashFlows));
        $terminal = $this->pv->terminalValue($terminalCashFlow, $calculation->discount_rate, $terminalGrowthRate, $period);
        $mid = round((float) $calculation->result['present_value'] + $terminal, 2);

        $calculation->forceFill([
            'result' => [
                ...$calculation->result,
                'terminal_value' => $terminal,
                'dcf_present_value' => $mid,
                'terminal_growth_rate' => $terminalGrowthRate,
            ],
        ])->save();

        return [
            'method' => 'dcf',
            'low' => round($mid * 0.9, 2),
            'mid' => $mid,
            'high' => round($mid * 1.1, 2),
            'terminal_value' => $terminal,
            'cash_flow_pv' => (float) $calculation->result['present_value'],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    private function snapshotDisclosures(FinancialSnapshot $snapshot, array $options): array
    {
        $maxAgeDays = max(0, (int) ($options['snapshot_stale_after_days'] ?? 180));

        if ($maxAgeDays === 0 || ! $snapshot->period_end) {
            return [];
        }

        $ageDays = $snapshot->period_end->diffInDays(now());

        if ($ageDays <= $maxAgeDays) {
            return [];
        }

        return [[
            'type' => 'stale_snapshot',
            'severity' => 'medium',
            'age_days' => $ageDays,
            'threshold_days' => $maxAgeDays,
            'message' => "Latest accounting snapshot is {$ageDays} days old, beyond the {$maxAgeDays}-day valuation freshness threshold.",
            'source_reference' => "financial_snapshot:{$snapshot->id}",
        ]];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    private function professionalDisclosures(array $options, PvCalculation $pvCalculation, float $terminalGrowthRate): array
    {
        $basisOfValue = $this->disclosureText(
            $options['basis_of_value'] ?? null,
            'Indicative market value range for advisory planning',
        );
        $purpose = $this->disclosureText(
            $options['valuation_purpose'] ?? $options['purpose'] ?? null,
            'Advisor-led planning, value improvement, and negotiation preparation',
        );
        $premiseOfValue = $this->disclosureText(
            $options['premise_of_value'] ?? null,
            'Going concern',
        );
        $asAt = now()->toDateString();

        return [
            [
                'type' => 'valuation_scope',
                'severity' => 'high',
                'engagement_type' => 'indicative_advisory_valuation',
                'message' => 'Prepared as an indicative advisory valuation for planning use only; it is not an AES-2/APES 225 independent business valuation engagement.',
                'source_reference' => 'valuation_disclosures:engagement_type',
            ],
            [
                'type' => 'basis_and_purpose',
                'severity' => 'high',
                'basis_of_value' => $basisOfValue,
                'valuation_purpose' => $purpose,
                'premise_of_value' => $premiseOfValue,
                'valuation_date' => $asAt,
                'message' => "Basis of value: {$basisOfValue}. Purpose: {$purpose}. Premise: {$premiseOfValue}. Valuation date: {$asAt}.",
                'source_reference' => 'valuation_disclosures:basis_purpose_premise',
            ],
            [
                'type' => 'reliance_limitations',
                'severity' => 'high',
                'message' => 'Do not rely on this output for litigation, tax, matrimonial, statutory, lending, insolvency, or fairness-opinion purposes without a separately scoped valuation engagement.',
                'source_reference' => 'valuation_disclosures:reliance_limitations',
            ],
            [
                'type' => 'method_scope',
                'severity' => 'medium',
                'message' => 'The value range triangulates SDE, EBITDA, and DCF/PV methods. Asset floor and surplus-asset checks are sanity checks unless a separately scoped asset approach is commissioned.',
                'source_reference' => 'valuation_disclosures:method_scope',
            ],
            [
                'type' => 'dcf_terminal_value',
                'severity' => 'medium',
                'terminal_growth_rate' => round($terminalGrowthRate, 4),
                'pv_calculation_id' => (string) $pvCalculation->getKey(),
                'message' => sprintf(
                    'DCF includes an explicit terminal-value assumption using %.2f%% terminal growth; advisor must confirm this is suitable for the business maturity and risk profile.',
                    $terminalGrowthRate * 100,
                ),
                'source_reference' => 'pv_calculation:'.$pvCalculation->getKey().':terminal_value',
            ],
        ];
    }

    private function disclosureText(mixed $value, string $fallback): string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return $fallback;
    }

    /**
     * @return array{sde:float, ebitda:float, dcf:float}
     */
    private function methodWeights(mixed $weights): array
    {
        $raw = is_array($weights) ? $weights : [];

        $out = [
            'sde' => max(0.0, (float) ($raw['sde'] ?? 1)),
            'ebitda' => max(0.0, (float) ($raw['ebitda'] ?? 1)),
            'dcf' => max(0.0, (float) ($raw['dcf'] ?? 1)),
        ];
        $total = array_sum($out);

        if ($total <= 0.0) {
            $out = ['sde' => 1.0, 'ebitda' => 1.0, 'dcf' => 1.0];
            $total = 3.0;
        }

        return [
            'sde' => $out['sde'] / $total,
            'ebitda' => $out['ebitda'] / $total,
            'dcf' => $out['dcf'] / $total,
        ];
    }

    /**
     * @param  array{sde:float, ebitda:float, dcf:float}  $weights
     * @return array{selected_method:string|null, rationale:string, weights:array{sde:float, ebitda:float, dcf:float}}
     */
    private function methodRationale(array $weights, mixed $input): array
    {
        $payload = is_array($input) ? $input : [];
        $selectedMethod = $payload['selected_method'] ?? null;

        return [
            'selected_method' => is_string($selectedMethod) && in_array($selectedMethod, ['sde', 'ebitda', 'dcf'], true)
                ? $selectedMethod
                : array_search(max($weights), $weights, true),
            'rationale' => (string) ($payload['rationale'] ?? 'Valuation reconciles SDE, EBITDA, and DCF using advisor-confirmed method weights.'),
            'weights' => $weights,
        ];
    }

    /**
     * @param  array<int, array{method:string, low:float, mid:float, high:float}>  $methods
     * @param  array<int, array{amount:float}>  $adjustments
     * @param  array{sde:float, ebitda:float, dcf:float}  $weights
     * @return array{low:float, mid:float, high:float}
     */
    private function reconcileWeighted(array $methods, array $adjustments, array $weights): array
    {
        $adjustmentTotal = array_sum(array_column($adjustments, 'amount'));
        $weighted = ['low' => 0.0, 'mid' => 0.0, 'high' => 0.0];

        foreach ($methods as $method) {
            $weight = $weights[$method['method']] ?? 0.0;

            foreach (array_keys($weighted) as $key) {
                $weighted[$key] += (float) $method[$key] * $weight;
            }
        }

        return [
            'low' => round($weighted['low'] + $adjustmentTotal, 2),
            'mid' => round($weighted['mid'] + $adjustmentTotal, 2),
            'high' => round($weighted['high'] + $adjustmentTotal, 2),
        ];
    }

    /**
     * @param  array{low:float, mid:float, high:float}  $enterpriseRange
     * @return array{enterprise_range:array{low:float, mid:float, high:float}, bridge_adjustments:array<string, mixed>, bridge_total:float, equity_range:array{low:float, mid:float, high:float}, explanation:string}
     */
    private function equityBridge(mixed $input, array $enterpriseRange): array
    {
        $payload = is_array($input) ? $input : [];
        $otherAdjustments = collect((array) ($payload['other_advisor_adjustments'] ?? []))
            ->filter(fn (mixed $adjustment): bool => is_array($adjustment))
            ->map(fn (array $adjustment): array => [
                'label' => (string) ($adjustment['label'] ?? 'Advisor equity bridge adjustment'),
                'amount' => round((float) ($adjustment['amount'] ?? 0), 2),
                'rationale' => (string) ($adjustment['rationale'] ?? 'Advisor supplied equity bridge adjustment.'),
            ])
            ->values()
            ->all();
        $debt = round(max(0.0, (float) ($payload['debt'] ?? $payload['interest_bearing_debt'] ?? 0)), 2);
        $surplusCash = round(max(0.0, (float) ($payload['surplus_cash'] ?? 0)), 2);
        $workingCapital = round((float) ($payload['normalised_working_capital'] ?? 0), 2);
        $otherTotal = round(array_sum(array_column($otherAdjustments, 'amount')), 2);
        $bridgeTotal = round($surplusCash + $workingCapital + $otherTotal - $debt, 2);

        return [
            'enterprise_range' => $enterpriseRange,
            'bridge_adjustments' => [
                'debt' => $debt,
                'surplus_cash' => $surplusCash,
                'normalised_working_capital' => $workingCapital,
                'other_advisor_adjustments' => $otherAdjustments,
            ],
            'bridge_total' => $bridgeTotal,
            'equity_range' => [
                'low' => round($enterpriseRange['low'] + $bridgeTotal, 2),
                'mid' => round($enterpriseRange['mid'] + $bridgeTotal, 2),
                'high' => round($enterpriseRange['high'] + $bridgeTotal, 2),
            ],
            'explanation' => 'Enterprise value is reconciled first, then debt, surplus cash, normalised working capital, and advisor bridge adjustments convert enterprise value to equity value.',
        ];
    }

    /**
     * @return array{rows:array<int, array{discount_rate:float, terminal_growth_rate:float, value:float|null, note:string|null}>, source_attributions:array<int, array{claim:string, source_reference:string}>}
     */
    private function dcfSensitivity(
        PvCalculation $calculation,
        float $terminalGrowthRate,
        mixed $discountRates,
        mixed $terminalGrowthRates,
    ): array {
        $cashFlows = [];

        foreach (array_values((array) ($calculation->inputs['cash_flows'] ?? [])) as $index => $cashFlow) {
            $cashFlows[$index + 1] = (float) data_get($cashFlow, 'amount', 0);
        }

        $rates = $this->sensitivityRates($discountRates, $calculation->discount_rate, 0.02);
        $growthRates = $this->sensitivityRates($terminalGrowthRates, $terminalGrowthRate, 0.01, floorAtZero: true);
        $rows = [];

        foreach ($rates as $rate) {
            foreach ($growthRates as $growthRate) {
                if ($growthRate >= $rate) {
                    $rows[] = [
                        'discount_rate' => $rate,
                        'terminal_growth_rate' => $growthRate,
                        'value' => null,
                        'note' => 'Terminal growth must remain below the discount rate.',
                    ];

                    continue;
                }

                $period = max(1, count($cashFlows));
                $terminalCashFlow = (float) ($cashFlows[$period] ?? (end($cashFlows) ?: 0));
                $rows[] = [
                    'discount_rate' => $rate,
                    'terminal_growth_rate' => $growthRate,
                    'value' => round(
                        $this->pv->presentValue($cashFlows, $rate)
                        + $this->pv->terminalValue($terminalCashFlow, $rate, $growthRate, $period),
                        2,
                    ),
                    'note' => null,
                ];
            }
        }

        return [
            'rows' => $rows,
            'source_attributions' => [[
                'claim' => 'DCF sensitivity uses the valuation cash-flow forecast, discount-rate assumptions, and terminal-growth assumptions.',
                'source_reference' => 'pv_calculation:'.$calculation->getKey().':dcf_sensitivity',
            ]],
        ];
    }

    /**
     * @return array<int, float>
     */
    private function sensitivityRates(mixed $input, float $base, float $step, bool $floorAtZero = false): array
    {
        if (is_array($input) && $input !== []) {
            $rates = array_map('floatval', array_values(array_filter($input, 'is_numeric')));
        } else {
            $rates = [$base - $step, $base, $base + $step];
        }

        return collect($rates)
            ->map(fn (float $rate): float => round($floorAtZero ? max(0.0, $rate) : max(0.0001, $rate), 4))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{status:string, explanation:string, succession_plan_id:string|null, target_exit_pv_calculation_id:string|null}
     */
    private function successionComparison(Client $client, PvCalculation $valuationDcf, float $terminalGrowthRate): array
    {
        $plan = SuccessionPlan::query()
            ->with('targetExitPvCalculation')
            ->where('client_id', $client->getKey())
            ->latest()
            ->first();

        if (! $plan instanceof SuccessionPlan || ! $plan->targetExitPvCalculation instanceof PvCalculation) {
            return [
                'status' => 'not_available',
                'explanation' => 'No succession target-exit PV is available to compare with the business valuation DCF.',
                'succession_plan_id' => null,
                'target_exit_pv_calculation_id' => null,
            ];
        }

        return [
            'status' => 'documented_difference',
            'explanation' => sprintf(
                'Business valuation DCF includes an explicit terminal value using %.2f%% terminal growth. Succession target-exit PV discounts the owner target cash-flow plan only, so it intentionally excludes a separate terminal-value assumption unless those terminal assumptions are entered as target exit cash flows.',
                $terminalGrowthRate * 100,
            ),
            'succession_plan_id' => (string) $plan->getKey(),
            'target_exit_pv_calculation_id' => (string) $plan->targetExitPvCalculation->getKey(),
            'valuation_pv_calculation_id' => (string) $valuationDcf->getKey(),
        ];
    }

    /**
     * @return array<int, array{label:string, amount:float, rationale:string}>
     */
    private function adjustments(mixed $adjustments): array
    {
        if (! is_array($adjustments)) {
            return [];
        }

        return array_values(array_map(
            fn (array $adjustment): array => [
                'label' => (string) ($adjustment['label'] ?? 'Valuation adjustment'),
                'amount' => round((float) ($adjustment['amount'] ?? 0), 2),
                'rationale' => (string) ($adjustment['rationale'] ?? 'Advisor supplied valuation adjustment.'),
            ],
            array_filter($adjustments, 'is_array'),
        ));
    }

    /**
     * @param  array<int, array{low:float, mid:float, high:float}>  $methods
     * @param  array<int, array{amount:float}>  $adjustments
     * @return array{low:float, mid:float, high:float}
     */
    private function reconcile(array $methods, array $adjustments): array
    {
        $count = max(1, count($methods));
        $adjustmentTotal = array_sum(array_column($adjustments, 'amount'));

        return [
            'low' => round((array_sum(array_column($methods, 'low')) / $count) + $adjustmentTotal, 2),
            'mid' => round((array_sum(array_column($methods, 'mid')) / $count) + $adjustmentTotal, 2),
            'high' => round((array_sum(array_column($methods, 'high')) / $count) + $adjustmentTotal, 2),
        ];
    }

    /**
     * @return array<int, float>
     */
    private function projectCashFlows(float $baseCashFlow, float $growthRate): array
    {
        $cashFlows = [];

        foreach (range(1, 5) as $year) {
            $cashFlows[$year] = round($baseCashFlow * ((1 + $growthRate) ** ($year - 1)), 2);
        }

        return $cashFlows;
    }
}
