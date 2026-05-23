<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Enums\DiscountMethod;
use App\Enums\PvType;
use App\Models\BusinessValuation as BusinessValuationModel;
use App\Models\Client;
use App\Models\FinancialSnapshot;
use App\Models\PvCalculation;
use App\Models\ValuationMultiple;
use App\Services\Audit\AuditWriter;
use InvalidArgumentException;

final class BusinessValuation
{
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
        $dcfValue = $this->dcfRange($pvCalculation, (float) ($options['terminal_growth_rate'] ?? 0.02));
        $adjustments = $this->adjustments($options['adjustments'] ?? []);

        $reconciled = $this->reconcile([$sdeValue, $ebitdaValue, $dcfValue], $adjustments);
        $attributions = array_values(array_merge(
            $financials['attributions'],
            $sdeRange['source_attributions'],
            $ebitdaRange['source_attributions'],
            $pvCalculation->source_attributions,
        ));

        $valuation = BusinessValuationModel::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $pvCalculation->getKey(),
            'sde_value' => $sdeValue,
            'ebitda_value' => $ebitdaValue,
            'dcf_value' => $dcfValue,
            'reconciled_low' => $reconciled['low'],
            'reconciled_mid' => $reconciled['mid'],
            'reconciled_high' => $reconciled['high'],
            'adjustments' => $adjustments,
            'data_quality_disclaimer' => $financials['data_quality_disclaimer'],
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
     * @return array{sde:float, ebitda:float, cash_flows:array<int, float>, data_quality_disclaimer:?string, attributions:array<int, array{claim:string, source_reference:string}>}
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
            $ebitda = (float) data_get($snapshot->metrics, 'ebitda', $netProfit);
            $sde = (float) data_get($snapshot->metrics, 'sde', $ebitda + (float) ($options['owner_salary_adjustment'] ?? 0));
            $cashFlow = (float) data_get($snapshot->cash_flow, 'operating_cash_flow', $ebitda);

            return [
                'sde' => $sde,
                'ebitda' => $ebitda,
                'cash_flows' => $this->projectCashFlows($cashFlow, (float) ($options['growth_rate'] ?? 0.03)),
                'data_quality_disclaimer' => null,
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
