<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Enums\DiscountMethod;
use App\Enums\PvType;
use App\Models\Client;
use App\Models\RiskCost;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Facades\DB;

final class RiskCostPv implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['pv.risk_cost'];
    }

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
        return DB::transaction(function () use ($client, $risks, $discountMethod, $discountOptions): array {
            $models = [];
            $fingerprints = [];

            foreach ($risks as $risk) {
                $fingerprints[] = $this->sourceFingerprint($risk);
            }

            $fingerprints = array_values(array_unique($fingerprints));

            if ($fingerprints !== []) {
                RiskCost::query()
                    ->where('client_id', $client->getKey())
                    ->whereIn('source_fingerprint', $fingerprints)
                    ->whereNull('superseded_at')
                    ->update([
                        'superseded_at' => now(),
                        'superseded_reason' => 're_ranked',
                    ]);
            }

            foreach ($risks as $risk) {
                $financialImpact = (float) ($risk['financial_impact'] ?? 0);
                $probability = max(0.0, min(1.0, (float) ($risk['probability'] ?? 1)));
                $durationYears = max(1, min(10, (int) ($risk['duration_years'] ?? 1)));
                $penaltyRange = $this->penaltyRange($risk['statutory_penalty_range'] ?? null);
                $appliedImpact = max($financialImpact, $this->penaltyMidpoint($penaltyRange));
                $annualExpectedCost = round($appliedImpact * $probability, 2);
                $recurrence = $this->recurrence($risk, $penaltyRange);
                $cashFlowYears = $recurrence === 'one_off' ? 1 : $durationYears;
                $cashFlows = array_fill(1, $cashFlowYears, $annualExpectedCost);

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
                    'recurrence' => $recurrence,
                    'cash_flow_years' => $cashFlowYears,
                    'pv_of_cost' => (float) $calculation->result['present_value'],
                    'rank' => 0,
                    'source_fingerprint' => $this->sourceFingerprint($risk),
                    'source_attributions' => $this->sourceAttributions($risk, $calculation->source_attributions),
                ]);
            }

            return $this->rankModels($models);
        });
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
     * @param  array{low:float, high:float}|null  $penaltyRange
     */
    private function recurrence(array $risk, ?array $penaltyRange): string
    {
        $value = str_replace(
            '-',
            '_',
            mb_strtolower(trim((string) ($risk['recurrence'] ?? $risk['risk_recurrence'] ?? ''))),
        );

        if (in_array($value, ['one_off', 'one_time', 'single', 'statutory_penalty'], true)) {
            return 'one_off';
        }

        if (in_array($value, ['recurring', 'annual', 'ongoing'], true)) {
            return 'recurring';
        }

        return $penaltyRange === null ? 'recurring' : 'one_off';
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
     * @param  array<string, mixed>  $risk
     */
    private function sourceFingerprint(array $risk): string
    {
        return hash('sha256', implode('|', [
            mb_strtolower(trim((string) ($risk['title'] ?? 'Risk cost'))),
            $this->fingerprintSource($risk, 'advisor:risk_cost'),
        ]));
    }

    /**
     * @param  array<string, mixed>  $risk
     */
    private function fingerprintSource(array $risk, string $default): string
    {
        $source = (string) (
            $risk['source_fingerprint_key']
            ?? $risk['stable_source_reference']
            ?? $risk['source_reference']
            ?? $default
        );

        return mb_strtolower(trim($source)) ?: $default;
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
