<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Enums\DiscountMethod;
use App\Enums\PvType;
use App\Models\Client;
use App\Models\RiskCost;

final class RiskCostPv
{
    public function __construct(private readonly PvEngine $pv) {}

    /**
     * @param  array<int, array<string, mixed>>  $risks
     * @param  array<string, mixed>  $discountOptions
     * @return array<int, RiskCost>
     */
    public function rank(
        Client $client,
        array $risks,
        DiscountMethod $discountMethod = DiscountMethod::AdvisorConfigured,
        array $discountOptions = ['rate' => 0.12, 'rationale' => 'Advisor default risk-cost PV discount rate.'],
    ): array {
        $models = [];

        foreach ($risks as $risk) {
            $financialImpact = (float) ($risk['financial_impact'] ?? 0);
            $probability = max(0.0, min(1.0, (float) ($risk['probability'] ?? 1)));
            $durationYears = max(1, min(10, (int) ($risk['duration_years'] ?? 1)));
            $penaltyRange = $this->penaltyRange($risk['statutory_penalty_range'] ?? null);
            $appliedImpact = max($financialImpact, $this->penaltyMidpoint($penaltyRange));
            $annualExpectedCost = round($appliedImpact * $probability, 2);
            $cashFlows = array_fill(1, $durationYears, $annualExpectedCost);

            $calculation = $this->pv->calculate(
                client: $client,
                type: PvType::RiskCost,
                discountMethod: $discountMethod,
                cashFlows: $cashFlows,
                discountOptions: $discountOptions,
            );

            $models[] = RiskCost::query()->create([
                'client_id' => $client->getKey(),
                'analysis_finding_id' => $risk['analysis_finding_id'] ?? null,
                'pv_calculation_id' => $calculation->getKey(),
                'title' => (string) ($risk['title'] ?? 'Risk cost'),
                'financial_impact' => $financialImpact,
                'probability' => $probability,
                'duration_years' => $durationYears,
                'statutory_penalty_range' => $penaltyRange,
                'applied_impact' => $appliedImpact,
                'annual_expected_cost' => $annualExpectedCost,
                'pv_of_cost' => (float) $calculation->result['present_value'],
                'rank' => 0,
                'source_attributions' => $this->sourceAttributions($risk, $calculation->source_attributions),
            ]);
        }

        return $this->rankModels($models);
    }

    /**
     * @return array{low:float, high:float}|null
     */
    private function penaltyRange(mixed $range): ?array
    {
        if (! is_array($range)) {
            return null;
        }

        $low = (float) ($range['low'] ?? 0);
        $high = (float) ($range['high'] ?? 0);

        if ($low <= 0 && $high <= 0) {
            return null;
        }

        return [
            'low' => min($low, $high),
            'high' => max($low, $high),
        ];
    }

    /**
     * @param  array{low:float, high:float}|null  $range
     */
    private function penaltyMidpoint(?array $range): float
    {
        if ($range === null) {
            return 0.0;
        }

        return round(((float) $range['low'] + (float) $range['high']) / 2, 2);
    }

    /**
     * @param  array<string, mixed>  $risk
     * @param  array<int, array{claim:string, source_reference:string}>  $calculationAttributions
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function sourceAttributions(array $risk, array $calculationAttributions): array
    {
        $source = (string) ($risk['source_reference'] ?? 'advisor:risk_cost');

        return [
            [
                'claim' => 'Risk impact, probability, duration, and statutory range were supplied for PV ranking.',
                'source_reference' => $source,
            ],
            ...$calculationAttributions,
        ];
    }

    /**
     * @param  array<int, RiskCost>  $models
     * @return array<int, RiskCost>
     */
    private function rankModels(array $models): array
    {
        usort($models, fn (RiskCost $a, RiskCost $b): int => $b->pv_of_cost <=> $a->pv_of_cost);

        foreach ($models as $index => $model) {
            $model->forceFill(['rank' => $index + 1])->save();
            $models[$index] = $model->refresh();
        }

        return $models;
    }
}
