<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingConnection;
use App\Models\ClientTeamMember;
use App\Models\FinancialAlert;
use App\Models\FinancialSnapshot;
use App\Models\User;
use App\Notifications\FinancialAlertNotification;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class HealthMonitor
{
    public function __construct(
        private readonly FinancialSnapshotPuller $puller,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * @return array{connections_scanned:int, snapshots_pulled:int, alerts_created:int, failures:int}
     */
    public function run(string $cadence = 'daily'): array
    {
        $this->context->apply('system', []);

        $connections = AccountingConnection::query()
            ->with('client')
            ->where('status', AccountingConnection::STATUS_CONNECTED)
            ->whereNull('revoked_at')
            ->get();

        $snapshotsPulled = 0;
        $alertsCreated = 0;
        $failures = 0;

        foreach ($connections as $connection) {
            $previous = $this->latestSnapshot($connection);

            try {
                $current = $this->puller->pull($connection);
            } catch (Throwable $e) {
                $failures++;
                $this->audit->record('financial_monitoring.pull_failed', subject: $connection, after: [
                    'client_id' => $connection->client_id,
                    'provider' => $connection->provider,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $snapshotsPulled++;

            if (! $previous instanceof FinancialSnapshot) {
                continue;
            }

            foreach ($this->deteriorationSignals($previous, $current) as $signal) {
                $alert = $this->createAlert($connection, $previous, $current, $signal);

                if ($alert->wasRecentlyCreated) {
                    $this->notify($alert);
                    $alertsCreated++;
                }
            }
        }

        $this->audit->record('financial_monitoring.completed', after: [
            'cadence' => $cadence,
            'connections_scanned' => $connections->count(),
            'snapshots_pulled' => $snapshotsPulled,
            'alerts_created' => $alertsCreated,
            'failures' => $failures,
        ]);

        return [
            'connections_scanned' => $connections->count(),
            'snapshots_pulled' => $snapshotsPulled,
            'alerts_created' => $alertsCreated,
            'failures' => $failures,
        ];
    }

    private function latestSnapshot(AccountingConnection $connection): ?FinancialSnapshot
    {
        return FinancialSnapshot::query()
            ->where('accounting_connection_id', $connection->getKey())
            ->latest('pulled_at')
            ->latest('created_at')
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function deteriorationSignals(FinancialSnapshot $previous, FinancialSnapshot $current): array
    {
        return array_values(array_filter([
            $this->dropSignal(
                previous: $previous,
                current: $current,
                metric: 'revenue',
                path: 'profit_and_loss.revenue',
                label: 'Revenue',
                category: FinancialAlert::CATEGORY_PROFITABILITY,
                threshold: $this->threshold('revenue_drop_threshold'),
            ),
            $this->dropSignal(
                previous: $previous,
                current: $current,
                metric: 'net_profit',
                path: 'profit_and_loss.net_profit',
                label: 'Net profit',
                category: FinancialAlert::CATEGORY_PROFITABILITY,
                threshold: $this->threshold('net_profit_drop_threshold'),
            ),
            $this->dropSignal(
                previous: $previous,
                current: $current,
                metric: 'operating_cash_flow',
                path: 'cash_flow.operating_cash_flow',
                label: 'Operating cash flow',
                category: FinancialAlert::CATEGORY_CASH_FLOW,
                threshold: $this->threshold('cash_flow_drop_threshold'),
            ),
            $this->grossMarginSignal($previous, $current),
            $this->currentRatioSignal($previous, $current),
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dropSignal(
        FinancialSnapshot $previous,
        FinancialSnapshot $current,
        string $metric,
        string $path,
        string $label,
        string $category,
        float $threshold,
    ): ?array {
        $previousValue = $this->metricValue($previous, $path);
        $currentValue = $this->metricValue($current, $path);

        if ($previousValue === null || $currentValue === null || $previousValue <= 0) {
            return null;
        }

        $changePercent = ($currentValue - $previousValue) / abs($previousValue);
        if ($changePercent > -$threshold && ! ($metric === 'operating_cash_flow' && $currentValue < 0)) {
            return null;
        }

        return $this->signal(
            previous: $previous,
            current: $current,
            metric: $metric,
            path: $path,
            label: $label,
            category: $category,
            previousValue: $previousValue,
            currentValue: $currentValue,
            severity: $changePercent <= -0.5 || $currentValue < 0
                ? FinancialAlert::SEVERITY_CRITICAL
                : FinancialAlert::SEVERITY_WARNING,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function grossMarginSignal(FinancialSnapshot $previous, FinancialSnapshot $current): ?array
    {
        $previousValue = $this->metricValue($previous, 'metrics.gross_margin');
        $currentValue = $this->metricValue($current, 'metrics.gross_margin');

        if ($previousValue === null || $currentValue === null) {
            return null;
        }

        if (($previousValue - $currentValue) < $this->threshold('gross_margin_drop_points')) {
            return null;
        }

        return $this->signal(
            previous: $previous,
            current: $current,
            metric: 'gross_margin',
            path: 'metrics.gross_margin',
            label: 'Gross margin',
            category: FinancialAlert::CATEGORY_PROFITABILITY,
            previousValue: $previousValue,
            currentValue: $currentValue,
            severity: ($previousValue - $currentValue) >= 0.2
                ? FinancialAlert::SEVERITY_CRITICAL
                : FinancialAlert::SEVERITY_WARNING,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentRatioSignal(FinancialSnapshot $previous, FinancialSnapshot $current): ?array
    {
        $previousValue = $this->metricValue($previous, 'metrics.current_ratio');
        $currentValue = $this->metricValue($current, 'metrics.current_ratio');

        if ($previousValue === null || $currentValue === null) {
            return null;
        }

        if ($currentValue > $this->threshold('current_ratio_floor') || $currentValue >= $previousValue) {
            return null;
        }

        return $this->signal(
            previous: $previous,
            current: $current,
            metric: 'current_ratio',
            path: 'metrics.current_ratio',
            label: 'Current ratio',
            category: FinancialAlert::CATEGORY_LIQUIDITY,
            previousValue: $previousValue,
            currentValue: $currentValue,
            severity: $currentValue < 1.0
                ? FinancialAlert::SEVERITY_CRITICAL
                : FinancialAlert::SEVERITY_WARNING,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function signal(
        FinancialSnapshot $previous,
        FinancialSnapshot $current,
        string $metric,
        string $path,
        string $label,
        string $category,
        float $previousValue,
        float $currentValue,
        string $severity,
    ): array {
        $changeAmount = $currentValue - $previousValue;
        $changePercent = $previousValue === 0.0 ? null : $changeAmount / abs($previousValue);

        return [
            'metric' => $metric,
            'path' => $path,
            'label' => $label,
            'category' => $category,
            'severity' => $severity,
            'previous_value' => $previousValue,
            'current_value' => $currentValue,
            'change_amount' => $changeAmount,
            'change_percent' => $changePercent,
            'headline' => "{$label} deterioration detected",
            'detail' => sprintf(
                '%s moved from %s for period ending %s to %s for period ending %s.',
                $label,
                $this->formatValue($metric, $previousValue),
                $previous->period_end?->toDateString() ?? 'unknown',
                $this->formatValue($metric, $currentValue),
                $current->period_end?->toDateString() ?? 'unknown',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $signal
     */
    private function createAlert(
        AccountingConnection $connection,
        FinancialSnapshot $previous,
        FinancialSnapshot $current,
        array $signal,
    ): FinancialAlert {
        $alertKey = $this->alertKey($connection, $previous, $current, (string) $signal['metric']);

        return FinancialAlert::query()->firstOrCreate(
            ['alert_key' => $alertKey],
            [
                'client_id' => $connection->client_id,
                'accounting_connection_id' => $connection->getKey(),
                'previous_snapshot_id' => $previous->getKey(),
                'current_snapshot_id' => $current->getKey(),
                'category' => $signal['category'],
                'severity' => $signal['severity'],
                'metric' => $signal['metric'],
                'headline' => $signal['headline'],
                'detail' => $signal['detail'],
                'previous_value' => $signal['previous_value'],
                'current_value' => $signal['current_value'],
                'change_amount' => $signal['change_amount'],
                'change_percent' => $signal['change_percent'],
                'citation' => $this->citation($previous, $current, $signal),
                'surfaced_at' => now(),
            ],
        );
    }

    private function notify(FinancialAlert $alert): void
    {
        $recipients = $this->alertRecipients((string) $alert->client_id);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new FinancialAlertNotification($alert));
        }

        $alert->forceFill(['notified_at' => now()])->save();

        $this->audit->record('financial_alert.created', subject: $alert, after: [
            'client_id' => $alert->client_id,
            'metric' => $alert->metric,
            'severity' => $alert->severity,
            'previous_value' => $alert->previous_value,
            'current_value' => $alert->current_value,
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function alertRecipients(string $clientId): Collection
    {
        $superAdmins = User::query()
            ->where('user_type', User::TYPE_SUPER_ADMIN)
            ->get();

        $advisors = ClientTeamMember::query()
            ->with('user')
            ->where('client_id', $clientId)
            ->get()
            ->pluck('user')
            ->filter(fn (mixed $user): bool => $user instanceof User && $user->user_type === User::TYPE_ADVISOR)
            ->values();

        return $superAdmins
            ->merge($advisors)
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->unique(fn (User $user): string => (string) $user->getKey())
            ->values();
    }

    private function metricValue(FinancialSnapshot $snapshot, string $path): ?float
    {
        $payload = [
            'profit_and_loss' => $snapshot->profit_and_loss ?? [],
            'balance_sheet' => $snapshot->balance_sheet ?? [],
            'cash_flow' => $snapshot->cash_flow ?? [],
            'metrics' => $snapshot->metrics ?? [],
        ];
        $value = data_get($payload, $path);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<string, mixed>  $signal
     * @return array<string, mixed>
     */
    private function citation(FinancialSnapshot $previous, FinancialSnapshot $current, array $signal): array
    {
        $metric = (string) $signal['metric'];
        $path = (string) $signal['path'];

        return [
            'metric' => $metric,
            'label' => $signal['label'],
            'path' => $path,
            'previous' => [
                'snapshot_id' => $previous->id,
                'period_start' => $previous->period_start?->toDateString(),
                'period_end' => $previous->period_end?->toDateString(),
                'value' => $signal['previous_value'],
                'source_reference' => "financial_snapshot:{$previous->id}:{$path}",
            ],
            'current' => [
                'snapshot_id' => $current->id,
                'period_start' => $current->period_start?->toDateString(),
                'period_end' => $current->period_end?->toDateString(),
                'value' => $signal['current_value'],
                'source_reference' => "financial_snapshot:{$current->id}:{$path}",
            ],
            'change_amount' => $signal['change_amount'],
            'change_percent' => $signal['change_percent'],
        ];
    }

    private function alertKey(
        AccountingConnection $connection,
        FinancialSnapshot $previous,
        FinancialSnapshot $current,
        string $metric,
    ): string {
        return hash('sha256', implode('|', [
            'financial_alert',
            $connection->client_id,
            $connection->provider,
            $connection->getKey(),
            $previous->getKey(),
            $current->getKey(),
            $metric,
        ]));
    }

    private function threshold(string $key): float
    {
        return (float) config("integrations.accounting.monitoring.{$key}", 0);
    }

    private function formatValue(string $metric, float $value): string
    {
        if (str_contains($metric, 'margin')) {
            return number_format($value * 100, 1).'%';
        }

        if ($metric === 'current_ratio') {
            return number_format($value, 2);
        }

        return '$'.number_format($value, 2);
    }
}
