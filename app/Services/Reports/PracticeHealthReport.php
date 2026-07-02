<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ClientStatus;
use App\Enums\ProposalStatus;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\FinancialSnapshot;
use App\Models\ImprovementOpportunity;
use App\Models\PracticeHealthSnapshot;
use App\Models\Proposal;
use App\Models\RedFlag;
use App\Models\Report;
use App\Models\RiskCost;
use App\Models\User;
use App\Services\Analytics\FunnelTracker;
use App\Services\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PracticeHealthReport
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly FunnelTracker $funnels,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        return $this->forClientIds($this->visibleClientIds($user));
    }

    /**
     * A null client id list means all active clients.
     *
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    public function forClientIds(?array $clientIds): array
    {
        if ($clientIds === []) {
            return $this->empty();
        }

        $clients = $this->activeClients($clientIds);
        $ids = $clients->pluck('id')->map(fn (mixed $id): string => (string) $id)->values()->all();

        if ($ids === []) {
            return $this->empty();
        }

        $valuations = $this->latestValuations($ids);
        $snapshots = $this->latestFinancialSnapshots($ids);
        $improvements = $this->sumByClient(ImprovementOpportunity::class, 'pv_of_impact', $ids);
        $risks = $this->sumByClient(RiskCost::class, 'pv_of_cost', $ids);
        $proposalCounts = $this->proposalCounts($ids);
        $reportCounts = $this->reportCounts($ids);
        $openRedFlags = $this->openRedFlagCounts($ids);
        $funnelSummary = $this->funnels->summary($ids);

        $rows = $clients
            ->map(function (Client $client) use ($valuations, $snapshots, $improvements, $risks, $proposalCounts, $reportCounts, $openRedFlags): array {
                $clientId = (string) $client->getKey();
                $clientProposalCounts = $proposalCounts->get($clientId, collect());
                $valuation = $valuations->get($clientId);
                $snapshot = $snapshots->get($clientId);
                $currentPv = $this->round($valuation?->reconciled_mid ?? 0);
                $improvementPv = $this->round((float) ($improvements[$clientId] ?? 0));
                $riskMitigationPv = $this->round((float) ($risks[$clientId] ?? 0));
                $targetPv = $this->round($currentPv + $improvementPv + $riskMitigationPv);
                $revenue = $this->revenue($snapshot);

                return [
                    'client_id' => $clientId,
                    'client_name' => $client->legal_name,
                    'client_url' => route('advisor.clients.show', $clientId, absolute: false),
                    'current_pv' => $currentPv,
                    'improvement_pv' => $improvementPv,
                    'risk_mitigation_pv' => $riskMitigationPv,
                    'target_pv' => $targetPv,
                    'revenue_under_management' => $revenue,
                    'released_proposals' => (int) $clientProposalCounts->get(ProposalStatus::Released->value, 0),
                    'generated_reports' => (int) ($reportCounts[$clientId] ?? 0),
                    'open_red_flags' => (int) ($openRedFlags[$clientId] ?? 0),
                    'latest_valuation_at' => $valuation?->as_at?->toIso8601String(),
                    'latest_revenue_period_end' => $snapshot?->period_end?->toDateString(),
                ];
            })
            ->values();

        $proposalStatusTotals = $this->proposalStatusTotals($proposalCounts);

        return [
            'summary' => [
                'active_clients' => $rows->count(),
                'clients_with_pv' => $rows->filter(fn (array $row): bool => $row['target_pv'] > 0)->count(),
                'current_pv' => $this->round((float) $rows->sum('current_pv')),
                'improvement_pv' => $this->round((float) $rows->sum('improvement_pv')),
                'risk_mitigation_pv' => $this->round((float) $rows->sum('risk_mitigation_pv')),
                'target_pv' => $this->round((float) $rows->sum('target_pv')),
                'revenue_under_management' => $this->round((float) $rows->sum('revenue_under_management')),
            ],
            'phase_two' => [
                'released_proposals' => (int) ($proposalStatusTotals[ProposalStatus::Released->value] ?? 0),
                'open_red_flags' => (int) $rows->sum('open_red_flags'),
                'generated_reports' => (int) $rows->sum('generated_reports'),
                'funnel_events' => (int) data_get($funnelSummary, 'summary.events', 0),
                'funnel_worst_drop_off_rate' => (float) data_get($funnelSummary, 'summary.worst_drop_off_rate', 0),
                'proposal_statuses' => $proposalStatusTotals,
            ],
            'clients' => $rows->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function snapshotForUser(User $user): PracticeHealthSnapshot
    {
        $clientIds = $this->visibleClientIds($user);
        $scope = $user->user_type === User::TYPE_SUPER_ADMIN ? 'super_admin' : 'advisor';
        $advisorUserId = $scope === 'advisor' ? (int) $user->getKey() : null;

        return $this->createSnapshot($scope, $advisorUserId, $clientIds);
    }

    public function snapshotForPractice(): PracticeHealthSnapshot
    {
        return $this->createSnapshot('super_admin', null, null);
    }

    /**
     * @param  array<int, string>|null  $clientIds
     */
    private function createSnapshot(string $scope, ?int $advisorUserId, ?array $clientIds): PracticeHealthSnapshot
    {
        $metrics = $this->forClientIds($clientIds);
        $snapshot = PracticeHealthSnapshot::query()->create([
            'scope' => $scope,
            'advisor_user_id' => $advisorUserId,
            'client_ids' => $this->snapshotClientIds($clientIds, $metrics),
            'metrics' => $metrics,
            'generated_at' => now(),
        ]);

        $this->audit->record('practice_health.snapshot_created', subject: $snapshot, after: [
            'scope' => $scope,
            'advisor_user_id' => $advisorUserId,
            'active_clients' => data_get($metrics, 'summary.active_clients', 0),
            'target_pv' => data_get($metrics, 'summary.target_pv', 0),
            'revenue_under_management' => data_get($metrics, 'summary.revenue_under_management', 0),
        ]);

        return $snapshot->refresh();
    }

    /**
     * @return array<int, string>|null
     */
    private function visibleClientIds(User $user): ?array
    {
        if ($user->user_type === User::TYPE_SUPER_ADMIN) {
            return null;
        }

        return $user->accessibleClientIds();
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return EloquentCollection<int, Client>
     */
    private function activeClients(?array $clientIds): EloquentCollection
    {
        $query = Client::query()->orderBy('legal_name');

        if (is_array($clientIds)) {
            $query->whereIn('id', $clientIds);
        }

        if (Schema::hasColumn('clients', 'status')) {
            $query->where('status', ClientStatus::ACTIVE->value);
        }

        return $query->get();
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return Collection<string, BusinessValuation>
     */
    private function latestValuations(array $clientIds): Collection
    {
        return BusinessValuation::query()
            ->whereIn('client_id', $clientIds)
            ->orderBy('client_id')
            ->orderByDesc('as_at')
            ->latest()
            ->get()
            ->unique('client_id')
            ->keyBy(fn (BusinessValuation $valuation): string => (string) $valuation->client_id);
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return Collection<string, FinancialSnapshot>
     */
    private function latestFinancialSnapshots(array $clientIds): Collection
    {
        return FinancialSnapshot::query()
            ->whereIn('client_id', $clientIds)
            ->orderBy('client_id')
            ->orderByDesc('period_end')
            ->orderByDesc('pulled_at')
            ->get()
            ->unique('client_id')
            ->keyBy(fn (FinancialSnapshot $snapshot): string => (string) $snapshot->client_id);
    }

    /**
     * @param  class-string  $modelClass
     * @param  array<int, string>  $clientIds
     * @return array<string, float>
     */
    private function sumByClient(string $modelClass, string $column, array $clientIds): array
    {
        /** @var array<string, float> $totals */
        $totals = $modelClass::query()
            ->whereIn('client_id', $clientIds)
            ->select('client_id', DB::raw("sum({$column}) as aggregate"))
            ->groupBy('client_id')
            ->pluck('aggregate', 'client_id')
            ->map(fn (mixed $value): float => $this->round((float) $value))
            ->all();

        return $totals;
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return Collection<string, Collection<string, int>>
     */
    private function proposalCounts(array $clientIds): Collection
    {
        return Proposal::query()
            ->whereIn('client_id', $clientIds)
            ->select('client_id', 'status', DB::raw('count(*) as aggregate'))
            ->groupBy('client_id', 'status')
            ->get()
            ->groupBy(fn (Proposal $proposal): string => (string) $proposal->client_id)
            ->map(fn (Collection $rows): Collection => $rows->mapWithKeys(
                fn (Proposal $proposal): array => [
                    (string) $proposal->status->value => (int) $proposal->aggregate,
                ],
            ));
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return array<string, int>
     */
    private function reportCounts(array $clientIds): array
    {
        /** @var array<string, int> $counts */
        $counts = Report::query()
            ->whereIn('client_id', $clientIds)
            ->select('client_id', DB::raw('count(*) as aggregate'))
            ->groupBy('client_id')
            ->pluck('aggregate', 'client_id')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();

        return $counts;
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return array<string, int>
     */
    private function openRedFlagCounts(array $clientIds): array
    {
        /** @var array<string, int> $counts */
        $counts = RedFlag::query()
            ->whereIn('client_id', $clientIds)
            ->whereNull('resolved_at')
            ->select('client_id', DB::raw('count(*) as aggregate'))
            ->groupBy('client_id')
            ->pluck('aggregate', 'client_id')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();

        return $counts;
    }

    /**
     * @param  Collection<string, Collection<string, int>>  $proposalCounts
     * @return array<string, int>
     */
    private function proposalStatusTotals(Collection $proposalCounts): array
    {
        $totals = [];

        foreach ($proposalCounts as $counts) {
            foreach ($counts as $status => $count) {
                $totals[$status] = (int) ($totals[$status] ?? 0) + (int) $count;
            }
        }

        foreach (ProposalStatus::lifecycleStatuses() as $status) {
            $totals[$status->value] ??= 0;
        }

        ksort($totals);

        return $totals;
    }

    private function revenue(?FinancialSnapshot $snapshot): float
    {
        if (! $snapshot instanceof FinancialSnapshot) {
            return 0.0;
        }

        $value = data_get($snapshot->profit_and_loss, 'revenue');

        if (! is_numeric($value)) {
            $value = data_get($snapshot->metrics, 'revenue', 0);
        }

        return $this->round((float) $value);
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @param  array<string, mixed>  $metrics
     * @return array<int, string>
     */
    private function snapshotClientIds(?array $clientIds, array $metrics): array
    {
        if (is_array($clientIds)) {
            return array_values(array_map('strval', $clientIds));
        }

        return collect($metrics['clients'] ?? [])
            ->pluck('client_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(): array
    {
        return [
            'summary' => [
                'active_clients' => 0,
                'clients_with_pv' => 0,
                'current_pv' => 0.0,
                'improvement_pv' => 0.0,
                'risk_mitigation_pv' => 0.0,
                'target_pv' => 0.0,
                'revenue_under_management' => 0.0,
            ],
            'phase_two' => [
                'released_proposals' => 0,
                'open_red_flags' => 0,
                'generated_reports' => 0,
                'funnel_events' => 0,
                'funnel_worst_drop_off_rate' => 0.0,
                'proposal_statuses' => array_fill_keys(
                    array_map(fn (ProposalStatus $status): string => $status->value, ProposalStatus::lifecycleStatuses()),
                    0,
                ),
            ],
            'clients' => [],
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
