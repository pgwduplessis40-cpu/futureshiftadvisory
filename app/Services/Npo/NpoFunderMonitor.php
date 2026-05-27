<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Models\Client;
use App\Models\ClientFunderAlert;
use App\Models\ClientFunderRecord;
use App\Models\Funder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class NpoFunderMonitor
{
    /**
     * @param  array<int, string>|null  $clientIds
     * @return EloquentCollection<int, ClientFunderAlert>
     */
    public function syncAlerts(?array $clientIds = null, ?CarbonInterface $at = null): EloquentCollection
    {
        $at ??= now();
        $created = new EloquentCollection;

        ClientFunderRecord::query()
            ->with('funder')
            ->when(is_array($clientIds), fn ($query) => $query->whereIn('client_id', $clientIds))
            ->orderBy('id')
            ->get()
            ->each(function (ClientFunderRecord $record) use ($at, $created): void {
                foreach ($this->alertsForRecord($record, $at) as $alert) {
                    $created->push($alert);
                }
            });

        return $created;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function clientSummary(Client $client): ?array
    {
        $records = ClientFunderRecord::query()
            ->with('funder')
            ->where('client_id', $client->getKey())
            ->latest('period_end')
            ->limit(8)
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        $alerts = ClientFunderAlert::query()
            ->with('clientFunderRecord.funder')
            ->where('client_id', $client->getKey())
            ->whereNull('resolved_at')
            ->orderByRaw("case severity when 'critical' then 0 when 'high' then 1 else 2 end")
            ->orderBy('due_on')
            ->limit(8)
            ->get();

        return [
            'records' => $records->map(fn (ClientFunderRecord $record): array => $this->recordPayload($record))->values()->all(),
            'alerts' => $alerts->map(fn (ClientFunderAlert $alert): array => $this->alertPayload($alert))->values()->all(),
            'concentration' => $this->concentrationForClient($client),
        ];
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    public function advisorPanel(?array $clientIds): array
    {
        if ($clientIds === []) {
            return $this->emptyPanel();
        }

        $alerts = ClientFunderAlert::query()
            ->with(['client', 'clientFunderRecord.funder'])
            ->whereNull('resolved_at')
            ->when(is_array($clientIds), fn ($query) => $query->whereIn('client_id', $clientIds))
            ->orderByRaw("case severity when 'critical' then 0 when 'high' then 1 else 2 end")
            ->orderBy('due_on')
            ->limit(10)
            ->get();
        $activeRecords = ClientFunderRecord::query()
            ->when(is_array($clientIds), fn ($query) => $query->whereIn('client_id', $clientIds))
            ->where(function ($query): void {
                $query->whereNull('period_end')
                    ->orWhere('period_end', '>=', now()->toDateString());
            })
            ->count();

        return [
            'summary' => [
                'active_records' => $activeRecords,
                'active_alerts' => $alerts->count(),
                'critical_alerts' => $alerts->where('severity', ClientFunderAlert::SEVERITY_CRITICAL)->count(),
            ],
            'alerts' => $alerts->map(fn (ClientFunderAlert $alert): array => [
                ...$this->alertPayload($alert),
                'client_name' => $alert->client?->legal_name,
                'client_url' => route('advisor.clients.show', $alert->client_id, absolute: false),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function concentrationForClient(Client $client): array
    {
        $records = ClientFunderRecord::query()
            ->with('funder')
            ->where('client_id', $client->getKey())
            ->where(function ($query): void {
                $query->whereNull('period_end')
                    ->orWhere('period_end', '>=', now()->toDateString());
            })
            ->get();

        return $this->concentration($records);
    }

    /**
     * @return array<string, mixed>
     */
    private function concentration(Collection $records): array
    {
        $total = (float) $records->sum('grant_amount');
        $largest = $records->sortByDesc('grant_amount')->first();
        $largestAmount = $largest instanceof ClientFunderRecord ? (float) $largest->grant_amount : 0.0;
        $ratio = $total > 0 ? round($largestAmount / $total, 4) : 0.0;

        return [
            'total_active_amount' => $total,
            'largest_funder_amount' => $largestAmount,
            'largest_funder_ratio' => $ratio,
            'largest_funder_name' => $largest instanceof ClientFunderRecord ? $largest->funder?->name : null,
            'risk_level' => $ratio >= 0.4 ? 'high' : ($ratio >= 0.25 ? 'medium' : 'low'),
            'source' => 'client_funder_records',
        ];
    }

    /**
     * @return array<int, ClientFunderAlert>
     */
    private function alertsForRecord(ClientFunderRecord $record, CarbonInterface $at): array
    {
        $alerts = [];
        $today = $at->copy()->startOfDay();

        if ($record->reporting_deadline instanceof CarbonInterface) {
            $days = (int) $today->diffInDays($record->reporting_deadline->copy()->startOfDay(), false);
            if ($days === 30) {
                $alerts[] = $this->raise($record, ClientFunderAlert::TYPE_REPORT_DUE_30, ClientFunderAlert::SEVERITY_MEDIUM, 'Funder report due in 30 days.', $record->reporting_deadline, $at);
            } elseif ($days === 7) {
                $alerts[] = $this->raise($record, ClientFunderAlert::TYPE_REPORT_DUE_7, ClientFunderAlert::SEVERITY_HIGH, 'Funder report due in 7 days.', $record->reporting_deadline, $at);
            } elseif ($days < 0) {
                $alerts[] = $this->raise($record, ClientFunderAlert::TYPE_REPORT_OVERDUE, ClientFunderAlert::SEVERITY_CRITICAL, 'Funder report is overdue.', $record->reporting_deadline, $at, daily: true);
            }
        }

        if ($record->next_application_window_opens_at instanceof CarbonInterface) {
            $opens = $record->next_application_window_opens_at->copy()->startOfDay();
            $closes = $record->next_application_window_closes_at?->copy()->startOfDay();
            $days = (int) $today->diffInDays($opens, false);

            if ($days === 60) {
                $alerts[] = $this->raise($record, ClientFunderAlert::TYPE_APPLICATION_WINDOW_60, ClientFunderAlert::SEVERITY_MEDIUM, 'Funder application window opens in 60 days.', $record->next_application_window_opens_at, $at);
            }

            if ($today->gte($opens) && ($closes === null || $today->lte($closes))) {
                $alerts[] = $this->raise($record, ClientFunderAlert::TYPE_APPLICATION_WINDOW_OPEN, ClientFunderAlert::SEVERITY_HIGH, 'Funder application window is open.', $record->next_application_window_opens_at, $at);
            }
        }

        if ($record->grant_expiry_at instanceof CarbonInterface) {
            $days = (int) $today->diffInDays($record->grant_expiry_at->copy()->startOfDay(), false);
            if ($days === 60) {
                $alerts[] = $this->raise($record, ClientFunderAlert::TYPE_GRANT_EXPIRY_60, ClientFunderAlert::SEVERITY_HIGH, 'Grant expires in 60 days.', $record->grant_expiry_at, $at);
            }
        }

        return $alerts;
    }

    private function raise(
        ClientFunderRecord $record,
        string $type,
        string $severity,
        string $message,
        CarbonInterface $dueOn,
        CarbonInterface $at,
        bool $daily = false,
    ): ClientFunderAlert {
        $keyDate = $daily ? $at->toDateString() : $dueOn->toDateString();

        /** @var ClientFunderAlert $alert */
        $alert = ClientFunderAlert::query()->firstOrCreate(
            ['alert_key' => "{$record->getKey()}:{$type}:{$keyDate}"],
            [
                'client_id' => $record->client_id,
                'client_funder_record_id' => $record->getKey(),
                'type' => $type,
                'severity' => $severity,
                'message' => $message,
                'due_on' => $dueOn->toDateString(),
                'triggered_at' => $at,
                'metadata' => [
                    'funder_id' => $record->funder_id,
                    'grant_name' => $record->grant_name,
                    'threshold_date' => $keyDate,
                ],
            ],
        );

        return $alert->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function recordPayload(ClientFunderRecord $record): array
    {
        return [
            'id' => $record->id,
            'funder_id' => $record->funder_id,
            'funder_name' => $record->funder?->name,
            'funder_needs_verification' => $record->funder instanceof Funder ? $record->funder->needsVerification() : false,
            'grant_name' => $record->grant_name,
            'grant_amount' => (float) $record->grant_amount,
            'currency' => $record->currency,
            'period_start' => $record->period_start?->toDateString(),
            'period_end' => $record->period_end?->toDateString(),
            'reporting_deadline' => $record->reporting_deadline?->toDateString(),
            'next_application_window_opens_at' => $record->next_application_window_opens_at?->toDateString(),
            'next_application_window_closes_at' => $record->next_application_window_closes_at?->toDateString(),
            'grant_expiry_at' => $record->grant_expiry_at?->toDateString(),
            'renewal_probability' => $record->renewal_probability,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function alertPayload(ClientFunderAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'client_id' => $alert->client_id,
            'record_id' => $alert->client_funder_record_id,
            'funder_name' => $alert->clientFunderRecord?->funder?->name,
            'type' => $alert->type,
            'severity' => $alert->severity,
            'message' => $alert->message,
            'due_on' => $alert->due_on?->toDateString(),
            'triggered_at' => $alert->triggered_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPanel(): array
    {
        return [
            'summary' => [
                'active_records' => 0,
                'active_alerts' => 0,
                'critical_alerts' => 0,
            ],
            'alerts' => [],
        ];
    }
}
