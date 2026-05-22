<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ImprovementOpportunity;
use App\Models\RiskCost;

final class PvWaterfallBuilder
{
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
        $improvement = (float) ImprovementOpportunity::query()
            ->where('client_id', $client->getKey())
            ->sum('pv_of_impact');
        $riskMitigation = (float) RiskCost::query()
            ->where('client_id', $client->getKey())
            ->sum('pv_of_cost');
        $target = round($current + $improvement + $riskMitigation, 2);

        return [
            'client_id' => $client->id,
            'client_name' => $client->legal_name,
            'business_valuation_id' => $valuation?->id,
            'current_pv' => round($current, 2),
            'improvement_pv' => round($improvement, 2),
            'risk_mitigation_pv' => round($riskMitigation, 2),
            'target_pv' => $target,
            'waterfall' => $this->steps($current, $improvement, $riskMitigation, $target),
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
     * @return array<int, array{key:string, label:string, kind:string, value:float, start:float, end:float}>
     */
    private function steps(float $current, float $improvement, float $riskMitigation, float $target): array
    {
        $afterImprovement = round($current + $improvement, 2);

        return [
            [
                'key' => 'current',
                'label' => 'Current PV',
                'kind' => 'absolute',
                'value' => round($current, 2),
                'start' => 0.0,
                'end' => round($current, 2),
            ],
            [
                'key' => 'improvements',
                'label' => 'Improvements',
                'kind' => 'increase',
                'value' => round($improvement, 2),
                'start' => round($current, 2),
                'end' => $afterImprovement,
            ],
            [
                'key' => 'risk_mitigation',
                'label' => 'Risk mitigation',
                'kind' => 'increase',
                'value' => round($riskMitigation, 2),
                'start' => $afterImprovement,
                'end' => $target,
            ],
            [
                'key' => 'target',
                'label' => 'Target PV',
                'kind' => 'total',
                'value' => $target,
                'start' => 0.0,
                'end' => $target,
            ],
        ];
    }
}
