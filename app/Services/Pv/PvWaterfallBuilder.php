<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ImprovementOpportunity;
use App\Models\RiskCost;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class PvWaterfallBuilder
{
    private const MAX_RECOMMENDATION_STEPS = 8;

    /**
     * A null client id list means all clients.
     *
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    public function forClients(?array $clientIds): array
    {
        if ($clientIds === []) {
            return $this->empty();
        }

        $query = Client::query()->orderBy('legal_name');

        if (is_array($clientIds)) {
            $query->whereIn('id', $clientIds);
        }

        $clients = $query->limit(12)->get();
        $items = $clients
            ->map(fn (Client $client): array => $this->forClient($client))
            ->filter(fn (array $item): bool => $item['current_pv'] > 0 || $item['target_pv'] > 0)
            ->values();

        return [
            'summary' => [
                'clients' => $items->count(),
                'current_pv' => round((float) $items->sum('current_pv'), 2),
                'improvement_pv' => round((float) $items->sum('improvement_pv'), 2),
                'risk_mitigation_pv' => round((float) $items->sum('risk_mitigation_pv'), 2),
                'target_pv' => round((float) $items->sum('target_pv'), 2),
            ],
            'clients' => $items->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forClient(Client $client): array
    {
        $valuation = BusinessValuation::query()
            ->where('client_id', $client->getKey())
            ->latest('as_at')
            ->latest()
            ->first();

        $current = $valuation instanceof BusinessValuation ? $valuation->reconciled_mid : 0.0;
        $improvements = ImprovementOpportunity::query()
            ->where('client_id', $client->getKey())
            ->with('pvCalculation')
            ->orderBy('rank')
            ->orderByDesc('pv_of_impact')
            ->get();
        $riskCosts = RiskCost::query()
            ->where('client_id', $client->getKey())
            ->with('pvCalculation')
            ->orderBy('rank')
            ->orderByDesc('pv_of_cost')
            ->get();
        $improvement = (float) $improvements->sum('pv_of_impact');
        $riskMitigation = (float) $riskCosts->sum('pv_of_cost');
        $target = round($current + $improvement + $riskMitigation, 2);

        return [
            'client_id' => $client->id,
            'client_name' => $client->legal_name,
            'business_valuation_id' => $valuation?->id,
            'current_pv' => round($current, 2),
            'improvement_pv' => round($improvement, 2),
            'risk_mitigation_pv' => round($riskMitigation, 2),
            'target_pv' => $target,
            'waterfall' => $this->steps($client, $current, $improvements, $riskCosts, $target),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(): array
    {
        return [
            'summary' => [
                'clients' => 0,
                'current_pv' => 0.0,
                'improvement_pv' => 0.0,
                'risk_mitigation_pv' => 0.0,
                'target_pv' => 0.0,
            ],
            'clients' => [],
        ];
    }

    /**
     * @param  EloquentCollection<int, ImprovementOpportunity>  $improvements
     * @param  EloquentCollection<int, RiskCost>  $riskCosts
     * @return array<int, array<string, mixed>>
     */
    private function steps(
        Client $client,
        float $current,
        EloquentCollection $improvements,
        EloquentCollection $riskCosts,
        float $target,
    ): array {
        $current = round($current, 2);
        $steps = [
            [
                'key' => 'current',
                'label' => 'Current PV',
                'kind' => 'absolute',
                'value' => $current,
                'start' => 0.0,
                'end' => $current,
                'is_remainder' => false,
            ],
        ];

        [$steps, $cursor] = $this->appendRecommendationSteps($steps, $client, $improvements, 'improvement', $current);
        [$steps, $cursor] = $this->appendRecommendationSteps($steps, $client, $riskCosts, 'risk_mitigation', $cursor);

        $steps[] = [
            'key' => 'target',
            'label' => 'Target PV',
            'kind' => 'total',
            'value' => $target,
            'start' => 0.0,
            'end' => $target,
            'is_remainder' => false,
        ];

        return $steps;
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @param  EloquentCollection<int, ImprovementOpportunity|RiskCost>  $items
     * @return array{0:array<int, array<string, mixed>>, 1:float}
     */
    private function appendRecommendationSteps(
        array $steps,
        Client $client,
        EloquentCollection $items,
        string $type,
        float $cursor,
    ): array {
        $visible = $items->take(self::MAX_RECOMMENDATION_STEPS);

        foreach ($visible as $item) {
            $step = $this->recommendationStep($client, $item, $type, $cursor);
            $steps[] = $step;
            $cursor = (float) $step['end'];
        }

        $remainder = $items->slice(self::MAX_RECOMMENDATION_STEPS)->values();

        if ($remainder->isNotEmpty()) {
            $value = round((float) $remainder->sum(
                $type === 'improvement' ? 'pv_of_impact' : 'pv_of_cost',
            ), 2);
            $end = round($cursor + $value, 2);

            $steps[] = [
                'key' => $type.'-remainder',
                'label' => $type === 'improvement'
                    ? sprintf('Other improvements (%d)', $remainder->count())
                    : sprintf('Other risk mitigation (%d)', $remainder->count()),
                'kind' => 'increase',
                'value' => $value,
                'start' => $cursor,
                'end' => $end,
                'recommendation_type' => $type,
                'is_remainder' => true,
                'remainder_count' => $remainder->count(),
                'drill_url' => null,
                'source_finding_id' => null,
                'pv_calculation_id' => null,
                'discount_rate' => null,
                'discount_method' => null,
                'duration_years' => null,
                'annual_benefit' => null,
                'annual_expected_cost' => null,
            ];

            $cursor = $end;
        }

        return [$steps, $cursor];
    }

    /**
     * @return array<string, mixed>
     */
    private function recommendationStep(
        Client $client,
        ImprovementOpportunity|RiskCost $item,
        string $type,
        float $start,
    ): array {
        $value = round($item instanceof ImprovementOpportunity ? (float) $item->pv_of_impact : (float) $item->pv_of_cost, 2);
        $end = round($start + $value, 2);
        $findingId = $item->analysis_finding_id === null ? null : (string) $item->analysis_finding_id;
        $pvCalculation = $item->pvCalculation;

        return [
            'key' => $type.'-'.$item->getKey(),
            'label' => $item->title,
            'kind' => 'increase',
            'value' => $value,
            'start' => round($start, 2),
            'end' => $end,
            'recommendation_type' => $type,
            'is_remainder' => false,
            'remainder_count' => null,
            'drill_url' => $findingId === null ? null : $this->findingDrillUrl($client, $findingId),
            'source_finding_id' => $findingId,
            'pv_calculation_id' => $item->pv_calculation_id,
            'discount_rate' => $pvCalculation?->discount_rate,
            'discount_method' => $pvCalculation?->discount_method?->value,
            'duration_years' => $item->duration_years,
            'annual_benefit' => $item instanceof ImprovementOpportunity ? round((float) $item->annual_benefit, 2) : null,
            'annual_expected_cost' => $item instanceof RiskCost ? round((float) $item->annual_expected_cost, 2) : null,
        ];
    }

    private function findingDrillUrl(Client $client, string $findingId): string
    {
        return route('advisor.clients.show', [
            'client' => $client,
            'focus' => 'analysis',
            'highlight' => $findingId,
        ], absolute: false);
    }
}
