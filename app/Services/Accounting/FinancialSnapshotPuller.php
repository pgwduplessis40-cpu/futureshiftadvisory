<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingConnection;
use App\Models\FinancialSnapshot;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class FinancialSnapshotPuller
{
    public function __construct(
        private readonly AccountingClientResolver $clients,
        private readonly AccountingConnector $connector,
        private readonly AuditWriter $audit,
    ) {}

    public function pull(AccountingConnection $connection, ?User $actor = null): FinancialSnapshot
    {
        if (! $connection->connected()) {
            throw new AccountingConnectionRevokedException('Cannot pull a financial snapshot from a revoked accounting connection.');
        }

        $token = $this->connector->decryptToken($connection);
        $payload = $this->clients
            ->client($connection->provider)
            ->financialSnapshot($connection, $token);

        $snapshot = FinancialSnapshot::query()->create([
            'client_id' => $connection->client_id,
            'accounting_connection_id' => $connection->getKey(),
            'provider' => $connection->provider,
            'period_start' => $this->date($payload['period_start'] ?? null)->toDateString(),
            'period_end' => $this->date($payload['period_end'] ?? null)->toDateString(),
            'source' => (string) ($payload['source'] ?? $connection->provider),
            'source_badge' => (string) ($payload['source_badge'] ?? 'unknown'),
            'degraded' => (bool) ($payload['degraded'] ?? false),
            'correlation_id' => $this->uuidOrNull($payload['correlation_id'] ?? null),
            'profit_and_loss' => (array) ($payload['profit_and_loss'] ?? []),
            'balance_sheet' => (array) ($payload['balance_sheet'] ?? []),
            'cash_flow' => (array) ($payload['cash_flow'] ?? []),
            'metrics' => (array) ($payload['metrics'] ?? []),
            'pulled_at' => now(),
        ]);

        $connection->forceFill(['last_snapshot_at' => $snapshot->pulled_at])->save();

        $this->audit->record('financial_snapshot.pulled', subject: $snapshot, actor: $actor, after: [
            'client_id' => $snapshot->client_id,
            'provider' => $snapshot->provider,
            'period_start' => $snapshot->period_start?->toDateString(),
            'period_end' => $snapshot->period_end?->toDateString(),
            'source_badge' => $snapshot->source_badge,
        ]);

        return $snapshot;
    }

    private function date(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return now();
    }

    private function uuidOrNull(mixed $value): ?string
    {
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }
}
