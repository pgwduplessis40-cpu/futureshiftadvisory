<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\DdEngagement;
use App\Models\DdIntegrationPlanItem;
use App\Models\DdRiskRegisterItem;
use App\Models\DdValuation;
use App\Models\DdWorkstream;
use App\Models\RiskCost;
use App\Services\Pv\RiskCostPv;
use App\Services\Reports\Data\DdMoneyRange;
use App\Services\Reports\Data\DueDiligenceRecommendation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Shared DD source, risk, valuation, and recommendation rules.
 *
 * Both Due Diligence and Acquisition Go/No-Go reports consume this service so
 * their decision basis cannot diverge as the report types are extracted.
 */
final class DueDiligenceReportSupport
{
    public function __construct(private readonly RiskCostPv $riskCosts) {}

    /** @return Collection<int, AnalysisFinding> */
    public function findings(DdEngagement $engagement): Collection
    {
        $workstreams = DdWorkstream::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->whereNotNull('analysis_run_id')
            ->with('analysisRun.findings')
            ->get();

        return $workstreams
            ->flatMap(function (DdWorkstream $workstream): Collection {
                $analysisRun = $workstream->analysisRun;

                if ($analysisRun === null) {
                    return collect();
                }

                return $analysisRun->findings;
            })
            ->values();
    }

    public function latestValuation(DdEngagement $engagement): ?DdValuation
    {
        return DdValuation::query()
            ->with('businessValuation', 'pvCalculation')
            ->where('dd_engagement_id', $engagement->getKey())
            ->latest('as_at')
            ->latest()
            ->first();
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return Collection<int, DdRiskRegisterItem>
     */
    public function refreshRiskRegister(DdEngagement $engagement, Collection $findings, ?DdValuation $valuation): Collection
    {
        DdRiskRegisterItem::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->delete();

        if ($findings->isEmpty()) {
            return collect();
        }

        $workstreamsByRun = DdWorkstream::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->whereNotNull('analysis_run_id')
            ->pluck('workstream', 'analysis_run_id');
        $baseValue = $this->valuationMidpoint($valuation) ?? 100000.0;
        $riskInputs = $findings
            ->map(function (AnalysisFinding $finding) use ($engagement, $workstreamsByRun, $baseValue): array {
                $workstream = (string) ($workstreamsByRun[$finding->analysis_run_id] ?? 'general');

                return [
                    'analysis_finding_id' => $finding->getKey(),
                    'title' => $finding->title,
                    'financial_impact' => $this->severityImpact($finding->severity, $baseValue),
                    'probability' => $this->severityProbability($finding->severity),
                    'duration_years' => 1,
                    'source_reference' => 'analysis_finding:'.$finding->getKey(),
                    'source_fingerprint_key' => $this->riskSourceKey($engagement, $finding, $workstream),
                ];
            })
            ->all();
        $riskCosts = collect($this->riskCosts->rank($engagement->client, $riskInputs));

        return $riskCosts
            ->map(function (RiskCost $riskCost) use ($engagement, $findings, $workstreamsByRun): DdRiskRegisterItem {
                /** @var AnalysisFinding|null $finding */
                $finding = $findings->firstWhere('id', $riskCost->analysis_finding_id);
                $riskLevel = $this->riskLevel($finding->severity);

                return DdRiskRegisterItem::query()->create([
                    'client_id' => $engagement->client_id,
                    'dd_engagement_id' => $engagement->getKey(),
                    'analysis_finding_id' => $finding?->getKey(),
                    'risk_cost_id' => $riskCost->getKey(),
                    'risk_level' => $riskLevel,
                    'category' => (string) ($finding === null ? 'general' : ($workstreamsByRun[$finding->analysis_run_id] ?? 'general')),
                    'title' => $riskCost->title,
                    'body' => $finding->body,
                    'financial_impact' => $riskCost->financial_impact,
                    'probability' => $riskCost->probability,
                    'pv_of_cost' => $riskCost->pv_of_cost,
                    'price_adjustment_nzd' => $this->priceAdjustment($riskLevel, $riskCost->pv_of_cost),
                    'rank' => $riskCost->rank,
                    'source_attributions' => $riskCost->source_attributions,
                ]);
            })
            ->sortBy('rank')
            ->values();
    }

    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @return Collection<int, DdIntegrationPlanItem>
     */
    public function refreshIntegrationPlan(DdEngagement $engagement, Collection $risks): Collection
    {
        DdIntegrationPlanItem::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->delete();

        $actions = $risks
            ->take(4)
            ->values()
            ->map(function (DdRiskRegisterItem $risk, int $index) use ($engagement): DdIntegrationPlanItem {
                $day = [1, 30, 60, 90][$index] ?? 90;

                return DdIntegrationPlanItem::query()->create([
                    'client_id' => $engagement->client_id,
                    'dd_engagement_id' => $engagement->getKey(),
                    'dd_risk_register_id' => $risk->getKey(),
                    'day' => $day,
                    'phase' => $day <= 30 ? 'stabilise' : ($day <= 60 ? 'integrate' : 'optimise'),
                    'action' => sprintf('Resolve %s DD risk: %s', str_replace('_', ' ', $risk->risk_level), $risk->title),
                    'owner' => 'advisor',
                    'priority' => in_array($risk->risk_level, [DdRiskRegisterItem::LEVEL_DEAL_KILLER, DdRiskRegisterItem::LEVEL_MAJOR], true) ? 'high' : 'medium',
                    'metadata' => [
                        'risk_level' => $risk->risk_level,
                        'pv_of_cost' => $risk->pv_of_cost,
                    ],
                ]);
            });

        $actions->push(DdIntegrationPlanItem::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->getKey(),
            'day' => 100,
            'phase' => 'review',
            'action' => 'Complete 100-day integration review against DD findings, price adjustments, and buyer-readiness assumptions.',
            'owner' => 'advisor',
            'priority' => $risks->contains(fn (DdRiskRegisterItem $risk): bool => $risk->risk_level === DdRiskRegisterItem::LEVEL_DEAL_KILLER) ? 'high' : 'medium',
            'metadata' => ['risk_count' => $risks->count()],
        ]));

        return $actions->sortBy('day')->values();
    }

    /** @param Collection<int, DdRiskRegisterItem> $risks */
    public function recommendation(Collection $risks, ?DdValuation $valuation): DueDiligenceRecommendation
    {
        $hasDealKiller = $risks->contains(fn (DdRiskRegisterItem $risk): bool => $risk->risk_level === DdRiskRegisterItem::LEVEL_DEAL_KILLER);
        $hasMajor = $risks->contains(fn (DdRiskRegisterItem $risk): bool => $risk->risk_level === DdRiskRegisterItem::LEVEL_MAJOR);
        /** @var mixed $buyerPosition */
        $buyerPosition = data_get($valuation?->buyer_position, 'position', 'no_valuation');

        if ($hasDealKiller) {
            return new DueDiligenceRecommendation(
                DdEngagement::RECOMMENDATION_ABANDON,
                'At least one deal-killer DD risk requires abandonment unless resolved outside the platform.',
            );
        }

        if ($hasMajor || $buyerPosition === 'renegotiate_or_walkaway') {
            return new DueDiligenceRecommendation(
                DdEngagement::RECOMMENDATION_RENEGOTIATE,
                'Major DD risk or valuation pressure indicates renegotiation before proceeding.',
            );
        }

        return new DueDiligenceRecommendation(
            DdEngagement::RECOMMENDATION_PROCEED,
            'No deal-killer or major DD risk is present and valuation signals do not require renegotiation.',
        );
    }

    public function valuationMidpoint(?DdValuation $valuation): ?float
    {
        /** @var mixed $normalised */
        $normalised = $valuation?->normalised_values;
        $mid = data_get($normalised, 'reconciled.mid') ?? data_get($normalised, 'mid');

        return is_numeric($mid) ? (float) $mid : null;
    }

    public function valuationRange(DdValuation $valuation, string $key): ?DdMoneyRange
    {
        /** @var mixed $normalised */
        $normalised = $valuation->normalised_values;
        $range = $key === 'reconciled'
            ? (data_get($normalised, 'reconciled') ?? $normalised)
            : data_get($normalised, $key);

        if (! is_array($range) && $valuation->businessValuation !== null) {
            /** @var mixed $sourceRange */
            $sourceRange = match ($key) {
                'sde_value' => $valuation->businessValuation->sde_value,
                'ebitda_value' => $valuation->businessValuation->ebitda_value,
                'dcf_value' => $valuation->businessValuation->dcf_value,
                default => null,
            };
            $range = is_array($sourceRange) ? $this->convertRangeToNzd($sourceRange, $valuation->source_to_nzd_rate) : null;
        }

        if (! is_array($range)) {
            return null;
        }

        $low = $range['low'] ?? null;
        $mid = $range['mid'] ?? null;
        $high = $range['high'] ?? null;

        if (! is_numeric($low) || ! is_numeric($mid) || ! is_numeric($high)) {
            return null;
        }

        return new DdMoneyRange(round((float) $low, 2), round((float) $mid, 2), round((float) $high, 2));
    }

    public function marketMultipleRange(DdValuation $valuation): ?DdMoneyRange
    {
        $ranges = array_values(array_filter([
            $this->valuationRange($valuation, 'sde_value'),
            $this->valuationRange($valuation, 'ebitda_value'),
        ]));

        if ($ranges === []) {
            return null;
        }

        $count = count($ranges);

        return new DdMoneyRange(
            low: round(array_sum(array_map(fn (DdMoneyRange $range): float => $range->low, $ranges)) / $count, 2),
            mid: round(array_sum(array_map(fn (DdMoneyRange $range): float => $range->mid, $ranges)) / $count, 2),
            high: round(array_sum(array_map(fn (DdMoneyRange $range): float => $range->high, $ranges)) / $count, 2),
        );
    }

    public function precedentTransactionRange(DdEngagement $engagement, DdValuation $valuation): ?DdMoneyRange
    {
        /** @var mixed $targetDetails */
        $targetDetails = $engagement->target_details;
        /** @var mixed $buyerPosition */
        $buyerPosition = $valuation->buyer_position;
        $precedents = data_get($targetDetails, 'precedent_transactions', []);

        if (! is_array($precedents) || $precedents === []) {
            $precedents = data_get($buyerPosition, 'precedent_transactions', []);
        }

        if (! is_array($precedents) || $precedents === []) {
            return null;
        }

        $precedentRange = $this->rangeFrom($precedents);

        if ($precedentRange instanceof DdMoneyRange) {
            return $precedentRange;
        }

        /** @var mixed $ebitdaInput */
        $ebitdaInput = $valuation->businessValuation?->ebitda_value;
        $ebitda = data_get($ebitdaInput, 'input');
        $values = [];

        foreach ($precedents as $precedent) {
            if (! is_array($precedent)) {
                continue;
            }

            $amount = $precedent['enterprise_value_nzd'] ?? $precedent['value_nzd'] ?? $precedent['amount_nzd'] ?? null;

            if (! is_numeric($amount) && is_numeric($precedent['amount'] ?? null)) {
                $amount = (float) $precedent['amount'] * $valuation->source_to_nzd_rate;
            }

            if (! is_numeric($amount) && is_numeric($precedent['multiple'] ?? null) && is_numeric($ebitda)) {
                $amount = (float) $precedent['multiple'] * (float) $ebitda * $valuation->source_to_nzd_rate;
            }

            if (is_numeric($amount)) {
                $values[] = round((float) $amount, 2);
            }
        }

        if ($values === []) {
            return null;
        }

        return new DdMoneyRange(
            low: round(min($values), 2),
            mid: round(array_sum($values) / count($values), 2),
            high: round(max($values), 2),
        );
    }

    public function adjustmentTotal(mixed ...$groups): float
    {
        $total = 0.0;

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $items = array_key_exists('amount', $group) || array_key_exists('value', $group)
                ? [$group]
                : array_values(array_filter($group, 'is_array'));

            foreach ($items as $item) {
                $amount = $item['amount'] ?? $item['value'] ?? null;

                if (is_numeric($amount)) {
                    $total += (float) $amount;
                }
            }
        }

        return round($total, 2);
    }

    public function valueWalkNote(DdValuation $valuation): string
    {
        /** @var mixed $buyerPosition */
        $buyerPosition = $valuation->buyer_position;
        $walk = data_get($buyerPosition, 'value_walk');

        if (! is_array($walk)) {
            return '';
        }

        $standaloneMid = data_get($walk, 'standalone_value_range_nzd.mid');
        $buyerSpecificMid = data_get($walk, 'buyer_specific_value_range_nzd.mid');

        return sprintf(
            "\nValue walk: standalone midpoint %s; deal-structure adjustment %s; synergy adjustment %s; buyer-specific midpoint %s. Standalone and buyer-specific values are deliberately separated.",
            $this->money($standaloneMid),
            $this->money($walk['deal_structure_adjustment_nzd'] ?? null),
            $this->money($walk['synergy_adjustment_nzd'] ?? null),
            $this->money($buyerSpecificMid),
        );
    }

    private function riskSourceKey(DdEngagement $engagement, AnalysisFinding $finding, string $workstream): string
    {
        /** @var mixed $attributions */
        $attributions = $finding->attributions;
        $stableSource = is_array($attributions)
            ? collect($attributions)
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): string => (string) ($item['source_reference'] ?? ''))
                ->first(fn (string $source): bool => $source !== '' && ! str_starts_with($source, 'analysis_finding:'))
            : null;
        $basis = implode('|', [
            (string) $engagement->getKey(),
            $workstream,
            Str::lower(trim((string) $finding->title)),
            is_string($stableSource) && $stableSource !== '' ? $stableSource : Str::lower(trim((string) $finding->body)),
        ]);

        return 'dd_risk:'.hash('sha256', $basis);
    }

    /**
     * @param  array<array-key, mixed>  $range
     * @return array<array-key, mixed>
     */
    private function convertRangeToNzd(array $range, float $rate): array
    {
        foreach (['low', 'mid', 'high'] as $point) {
            if (is_numeric($range[$point] ?? null)) {
                $range[$point] = round((float) $range[$point] * $rate, 2);
            }
        }

        return $range;
    }

    /** @param array<array-key, mixed> $values */
    private function rangeFrom(array $values): ?DdMoneyRange
    {
        $low = $values['low'] ?? null;
        $mid = $values['mid'] ?? null;
        $high = $values['high'] ?? null;

        if (! is_numeric($low) || ! is_numeric($mid) || ! is_numeric($high)) {
            return null;
        }

        return new DdMoneyRange(round((float) $low, 2), round((float) $mid, 2), round((float) $high, 2));
    }

    private function severityImpact(FindingSeverity $severity, float $baseValue): float
    {
        $ratio = match ($severity) {
            FindingSeverity::Critical => 0.30,
            FindingSeverity::High => 0.16,
            FindingSeverity::Medium => 0.08,
            FindingSeverity::Low => 0.03,
            FindingSeverity::Info => 0.01,
        };

        return round(max(10000.0, $baseValue * $ratio), 2);
    }

    private function severityProbability(FindingSeverity $severity): float
    {
        return match ($severity) {
            FindingSeverity::Critical => 0.85,
            FindingSeverity::High => 0.65,
            FindingSeverity::Medium => 0.45,
            FindingSeverity::Low => 0.25,
            FindingSeverity::Info => 0.10,
        };
    }

    private function riskLevel(FindingSeverity $severity): string
    {
        return match ($severity) {
            FindingSeverity::Critical => DdRiskRegisterItem::LEVEL_DEAL_KILLER,
            FindingSeverity::High => DdRiskRegisterItem::LEVEL_MAJOR,
            FindingSeverity::Medium => DdRiskRegisterItem::LEVEL_MINOR,
            FindingSeverity::Low, FindingSeverity::Info => DdRiskRegisterItem::LEVEL_INFORMATIONAL,
        };
    }

    private function priceAdjustment(string $riskLevel, float $pvOfCost): float
    {
        $ratio = match ($riskLevel) {
            DdRiskRegisterItem::LEVEL_DEAL_KILLER => 1.0,
            DdRiskRegisterItem::LEVEL_MAJOR => 0.60,
            DdRiskRegisterItem::LEVEL_MINOR => 0.20,
            default => 0.0,
        };

        return round($pvOfCost * $ratio, 2);
    }

    private function money(mixed $value): string
    {
        return is_numeric($value) ? 'NZD '.number_format((float) $value, 0) : 'n/a';
    }
}
