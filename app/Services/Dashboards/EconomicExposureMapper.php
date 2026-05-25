<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\FinancialSnapshot;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

final class EconomicExposureMapper implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['economic.exposure'];
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    public function forIndicator(string $indicator, ?array $clientIds = null): array
    {
        return match ($indicator) {
            'cpi_annual' => $this->forKey('cpi', $clientIds),
            'ocr' => $this->forKey('ocr', $clientIds),
            'minimum_wage', 'living_wage' => $this->unavailable('wage', 'classification_not_captured'),
            default => $this->unavailable($indicator, 'unsupported_indicator'),
        };
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    public function forExchangeRate(string $baseCurrency, string $quoteCurrency, ?array $clientIds = null): array
    {
        return $this->unavailable(
            'fx',
            'classification_not_captured',
            strtoupper($baseCurrency).'/'.strtoupper($quoteCurrency),
        );
    }

    /**
     * @return array<int, string>
     */
    public function supportedFilterKeys(): array
    {
        return ['cpi', 'ocr'];
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    public function forKey(string $key, ?array $clientIds = null): array
    {
        return match ($key) {
            'cpi' => $this->cpi($clientIds),
            'ocr' => $this->ocr($clientIds),
            'wage' => $this->unavailable('wage', 'classification_not_captured'),
            'fx' => $this->unavailable('fx', 'classification_not_captured'),
            default => $this->unavailable($key, 'unsupported_indicator'),
        };
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    private function cpi(?array $clientIds): array
    {
        $ids = $this->activeClientIds($clientIds);

        return $this->supported(
            key: 'cpi',
            label: 'CPI',
            clientIds: $ids,
            unknownCount: 0,
            notExposedCount: 0,
        );
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    private function ocr(?array $clientIds): array
    {
        $activeIds = $this->activeClientIds($clientIds);
        $latestSnapshots = FinancialSnapshot::query()
            ->whereIn('client_id', $activeIds)
            ->orderBy('client_id')
            ->orderByDesc('period_end')
            ->orderByDesc('pulled_at')
            ->get()
            ->unique('client_id')
            ->keyBy(fn (FinancialSnapshot $snapshot): string => (string) $snapshot->client_id);

        $exposedIds = [];
        $notExposed = 0;
        $unknown = 0;

        foreach ($activeIds as $clientId) {
            $snapshot = $latestSnapshots->get($clientId);

            if (! $snapshot instanceof FinancialSnapshot) {
                $unknown++;

                continue;
            }

            $debt = $this->debtValue($snapshot);

            if ($debt === null) {
                $unknown++;
            } elseif ($debt > 0) {
                $exposedIds[] = $clientId;
            } else {
                $notExposed++;
            }
        }

        return $this->supported(
            key: 'ocr',
            label: 'OCR',
            clientIds: $exposedIds,
            unknownCount: $unknown,
            notExposedCount: $notExposed,
        );
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<int, string>
     */
    private function activeClientIds(?array $clientIds): array
    {
        return $this->baseClientQuery($clientIds)
            ->where('status', ClientStatus::ACTIVE->value)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return Builder<Client>
     */
    private function baseClientQuery(?array $clientIds): Builder
    {
        $query = Client::query();

        if (is_array($clientIds)) {
            if ($clientIds === []) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereIn('id', $clientIds);
        }

        return $query;
    }

    private function debtValue(FinancialSnapshot $snapshot): ?float
    {
        $payload = [
            'balance_sheet' => $snapshot->balance_sheet ?? [],
            'cash_flow' => $snapshot->cash_flow ?? [],
            'metrics' => $snapshot->metrics ?? [],
        ];

        foreach ((array) config('dashboards.economic_exposure.ocr.debt_paths', []) as $path) {
            if (! is_string($path) || ! Arr::has($payload, $path)) {
                continue;
            }

            $value = data_get($payload, $path);

            return is_numeric($value) ? (float) $value : null;
        }

        return null;
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return array<string, mixed>
     */
    private function supported(
        string $key,
        string $label,
        array $clientIds,
        int $unknownCount,
        int $notExposedCount,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'supported' => true,
            'status' => 'supported',
            'reason' => null,
            'exposed_count' => count($clientIds),
            'unknown_count' => $unknownCount,
            'not_exposed_count' => $notExposedCount,
            'client_ids' => array_values($clientIds),
            'drill_url' => route('advisor.clients.index', ['exposed_to' => $key], absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(string $key, string $reason, ?string $label = null): array
    {
        return [
            'key' => $key,
            'label' => $label ?? strtoupper($key),
            'supported' => false,
            'status' => 'unavailable',
            'reason' => $reason,
            'exposed_count' => null,
            'unknown_count' => null,
            'not_exposed_count' => null,
            'client_ids' => [],
            'drill_url' => null,
        ];
    }
}
