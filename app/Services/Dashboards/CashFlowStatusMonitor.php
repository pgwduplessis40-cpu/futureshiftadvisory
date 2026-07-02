<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Models\Client;
use App\Models\FinancialAlert;
use App\Models\FinancialSnapshot;
use App\Models\StrategicBudget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

final class CashFlowStatusMonitor
{
    public const STATUS_POSITIVE = 'positive';

    public const STATUS_WATCH = 'watch';

    public const STATUS_NEGATIVE = 'negative';

    public const STATUS_UNKNOWN = 'unknown';

    private const CRITICAL_RUNWAY_MONTHS = 3;

    private const WATCH_RUNWAY_MONTHS = 6;

    /**
     * A null client id list means all clients.
     *
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    public function forClientIds(?array $clientIds): array
    {
        if ($clientIds === [] || ! Schema::hasTable('clients')) {
            return $this->empty();
        }

        $clients = $this->clients($clientIds);

        if ($clients->isEmpty()) {
            return $this->empty();
        }

        $ids = $clients
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
        $snapshots = $this->latestSnapshots($ids);
        $budgets = $this->latestBudgets($ids);
        $alerts = $this->latestCashFlowAlerts($ids);

        $items = $clients
            ->map(fn (Client $client): array => $this->statusForClient(
                $client,
                $snapshots[(string) $client->getKey()] ?? null,
                $budgets[(string) $client->getKey()] ?? null,
                $alerts[(string) $client->getKey()] ?? null,
            ))
            ->values();
        $counts = $items->countBy('status');
        $actionItems = $items
            ->filter(fn (array $item): bool => in_array($item['status'], [self::STATUS_NEGATIVE, self::STATUS_WATCH], true))
            ->sortBy(fn (array $item): int => $item['status'] === self::STATUS_NEGATIVE ? 0 : 1)
            ->values();

        return [
            'summary' => [
                'total' => $items->count(),
                'positive' => (int) ($counts[self::STATUS_POSITIVE] ?? 0),
                'watch' => (int) ($counts[self::STATUS_WATCH] ?? 0),
                'negative' => (int) ($counts[self::STATUS_NEGATIVE] ?? 0),
                'unknown' => (int) ($counts[self::STATUS_UNKNOWN] ?? 0),
                'action_required' => $actionItems->count(),
            ],
            'by_client' => $items
                ->keyBy('client_id')
                ->all(),
            'items' => $actionItems
                ->take(10)
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(): array
    {
        return [
            'summary' => [
                'total' => 0,
                'positive' => 0,
                'watch' => 0,
                'negative' => 0,
                'unknown' => 0,
                'action_required' => 0,
            ],
            'by_client' => [],
            'items' => [],
        ];
    }

    /**
     * @param  array<int, string>|null  $clientIds
     */
    private function clients(?array $clientIds): Collection
    {
        $query = Client::query()->orderBy('legal_name');

        if (is_array($clientIds)) {
            $query->whereIn('id', $clientIds);
        }

        return $query->get();
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return array<string, FinancialSnapshot>
     */
    private function latestSnapshots(array $clientIds): array
    {
        if ($clientIds === [] || ! Schema::hasTable('financial_snapshots')) {
            return [];
        }

        return FinancialSnapshot::query()
            ->whereIn('client_id', $clientIds)
            ->orderBy('client_id')
            ->latest('period_end')
            ->latest('pulled_at')
            ->get()
            ->unique('client_id')
            ->keyBy(fn (FinancialSnapshot $snapshot): string => (string) $snapshot->client_id)
            ->all();
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return array<string, StrategicBudget>
     */
    private function latestBudgets(array $clientIds): array
    {
        if ($clientIds === [] || ! Schema::hasTable('strategic_budgets')) {
            return [];
        }

        return StrategicBudget::query()
            ->whereIn('client_id', $clientIds)
            ->latest('updated_at')
            ->get()
            ->unique('client_id')
            ->keyBy(fn (StrategicBudget $budget): string => (string) $budget->client_id)
            ->all();
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return array<string, FinancialAlert>
     */
    private function latestCashFlowAlerts(array $clientIds): array
    {
        if ($clientIds === [] || ! Schema::hasTable('financial_alerts')) {
            return [];
        }

        return FinancialAlert::query()
            ->whereIn('client_id', $clientIds)
            ->where('category', FinancialAlert::CATEGORY_CASH_FLOW)
            ->where('surfaced_at', '>=', now()->subDays(90))
            ->latest('surfaced_at')
            ->get()
            ->unique('client_id')
            ->keyBy(fn (FinancialAlert $alert): string => (string) $alert->client_id)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function statusForClient(
        Client $client,
        ?FinancialSnapshot $snapshot,
        ?StrategicBudget $budget,
        ?FinancialAlert $alert,
    ): array {
        $operatingCashFlow = $this->operatingCashFlow($snapshot);
        $runwayMonths = $this->numberOrNull(data_get($budget?->computed ?? [], 'runway_months'));
        $runwayOpenEnded = (bool) data_get($budget?->computed ?? [], 'runway_open_ended', false);
        $cashFlowPositiveYear = $this->numberOrNull(data_get($budget?->computed ?? [], 'cash_flow_positive_year'));
        [$status, $reason, $source] = $this->statusReason(
            $operatingCashFlow,
            $runwayMonths,
            $runwayOpenEnded,
            $cashFlowPositiveYear,
            $alert,
        );

        return [
            'client_id' => (string) $client->getKey(),
            'client_name' => $client->legal_name,
            'client_url' => route('advisor.clients.show', $client, absolute: false),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'tone' => $this->tone($status),
            'reason' => $reason,
            'source' => $source,
            'latest_operating_cash_flow' => $operatingCashFlow,
            'latest_period_end' => $snapshot?->period_end?->toDateString(),
            'runway_months' => $runwayMonths,
            'runway_open_ended' => $runwayOpenEnded,
            'cash_flow_positive_year' => $cashFlowPositiveYear,
            'alert_headline' => $alert?->headline,
            'detail_url' => route('advisor.clients.show', $client, absolute: false).'#section-accounting',
        ];
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function statusReason(
        ?float $operatingCashFlow,
        ?float $runwayMonths,
        bool $runwayOpenEnded,
        ?float $cashFlowPositiveYear,
        ?FinancialAlert $alert,
    ): array {
        if ($operatingCashFlow !== null && $operatingCashFlow < 0) {
            return [
                self::STATUS_NEGATIVE,
                'Latest operating cash flow is negative and needs advisor action.',
                'Latest financial snapshot',
            ];
        }

        if ($alert instanceof FinancialAlert && $alert->severity === FinancialAlert::SEVERITY_CRITICAL) {
            return [
                self::STATUS_NEGATIVE,
                $alert->headline,
                'Cash-flow alert',
            ];
        }

        if ($runwayMonths !== null && ! $runwayOpenEnded && $runwayMonths < self::CRITICAL_RUNWAY_MONTHS) {
            return [
                self::STATUS_NEGATIVE,
                'Budget runway is below three months.',
                'Business Plan & Budget',
            ];
        }

        if ($runwayMonths !== null && ! $runwayOpenEnded && $runwayMonths < self::WATCH_RUNWAY_MONTHS) {
            return [
                self::STATUS_WATCH,
                'Budget runway is under six months.',
                'Business Plan & Budget',
            ];
        }

        if ($alert instanceof FinancialAlert) {
            return [
                self::STATUS_WATCH,
                $alert->headline,
                'Cash-flow alert',
            ];
        }

        if ($cashFlowPositiveYear === null && $runwayMonths !== null) {
            return [
                self::STATUS_WATCH,
                'Budget forecast has not yet shown a cash-flow-positive point.',
                'Business Plan & Budget',
            ];
        }

        if ($operatingCashFlow !== null || $runwayMonths !== null || $cashFlowPositiveYear !== null) {
            return [
                self::STATUS_POSITIVE,
                'No immediate cash-flow pressure detected from current actuals or budget forecast.',
                $operatingCashFlow !== null ? 'Latest financial snapshot' : 'Business Plan & Budget',
            ];
        }

        return [
            self::STATUS_UNKNOWN,
            'No cash-flow actuals or budget forecast are available yet.',
            'Financial data required',
        ];
    }

    private function operatingCashFlow(?FinancialSnapshot $snapshot): ?float
    {
        if (! $snapshot instanceof FinancialSnapshot) {
            return null;
        }

        return $this->numberOrNull(data_get($snapshot->cash_flow ?? [], 'operating_cash_flow'))
            ?? $this->numberOrNull(data_get($snapshot->metrics ?? [], 'operating_cash_flow'));
    }

    private function numberOrNull(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $cleaned = preg_replace('/[^0-9.\-]/', '', $value);

            return is_numeric($cleaned) ? (float) $cleaned : null;
        }

        return null;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_POSITIVE => 'Positive',
            self::STATUS_WATCH => 'Watch',
            self::STATUS_NEGATIVE => 'Negative',
            default => 'Unknown',
        };
    }

    private function tone(string $status): string
    {
        return match ($status) {
            self::STATUS_POSITIVE => 'positive',
            self::STATUS_WATCH => 'warning',
            self::STATUS_NEGATIVE => 'negative',
            default => 'muted',
        };
    }
}
