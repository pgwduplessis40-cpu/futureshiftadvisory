<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Models\IndustryWaccData;
use App\Models\LearningLayerRun;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\Mbie\Contracts\MbieClient;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IndustryWaccRefresher
{
    public const LAYER_ID = 22;

    public function __construct(
        private readonly MbieClient $mbie,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * @return array{run: LearningLayerRun, rates_refreshed: int, rows_superseded: int}
     */
    public function refresh(?CarbonInterface $fetchedAt = null, ?string $quarter = null): array
    {
        $fetchedAt ??= now();
        $quarter = $this->quarter($quarter, $fetchedAt);
        $this->context->apply('system', []);
        $records = $this->mbie->industryWaccRates();

        return DB::transaction(function () use ($records, $fetchedAt, $quarter): array {
            $created = 0;
            $superseded = 0;

            foreach ($records as $record) {
                if (! is_array($record)) {
                    continue;
                }

                $normalized = $this->normalize($record, $fetchedAt, $quarter);
                if ($normalized === null) {
                    continue;
                }

                $existing = IndustryWaccData::query()
                    ->where('record_hash', $normalized['record_hash'])
                    ->first();

                if ($existing instanceof IndustryWaccData) {
                    if ($existing->superseded_at === null) {
                        $existing->forceFill([
                            'fetched_at' => $fetchedAt,
                            'source_badge' => $normalized['source_badge'],
                            'degraded' => $normalized['degraded'],
                            'correlation_id' => $normalized['correlation_id'],
                            'payload' => $normalized['payload'],
                        ])->save();
                    }

                    continue;
                }

                $superseded += IndustryWaccData::query()
                    ->where('industry_code', $normalized['industry_code'])
                    ->where('source', $normalized['source'])
                    ->whereNull('superseded_at')
                    ->update(['superseded_at' => $fetchedAt, 'updated_at' => now()]);

                IndustryWaccData::query()->create($normalized);
                $created++;
            }

            $run = LearningLayerRun::query()->create([
                'layer_id' => self::LAYER_ID,
                'ran_at' => now(),
                'candidates_created' => 0,
                'window' => [
                    'quarter' => $quarter,
                    'fetched_at' => $fetchedAt->toIso8601String(),
                    'rates_refreshed' => $created,
                    'rows_superseded' => $superseded,
                    'automatic_application' => true,
                    'consumer' => 'DiscountRateResolver::industry_wacc',
                ],
                'status' => LearningLayerRun::STATUS_COMPLETED,
            ]);

            $this->audit->record('industry_wacc.refreshed', subject: $run, after: [
                'layer_id' => self::LAYER_ID,
                'quarter' => $quarter,
                'rates_refreshed' => $created,
                'rows_superseded' => $superseded,
            ]);

            return [
                'run' => $run,
                'rates_refreshed' => $created,
                'rows_superseded' => $superseded,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>|null
     */
    private function normalize(array $record, CarbonInterface $fetchedAt, string $quarter): ?array
    {
        $industryCode = strtoupper(trim((string) ($record['industry_code'] ?? '')));
        if ($industryCode === '') {
            return null;
        }

        $waccRate = $this->rate($record['wacc_rate'] ?? $record['rate'] ?? null);
        if ($waccRate === null) {
            return null;
        }

        $source = strtolower(trim((string) ($record['source'] ?? 'mbie')));
        $recordQuarter = $this->quarter(is_string($record['quarter'] ?? null) ? $record['quarter'] : null, $fetchedAt, $quarter);
        $hash = $this->recordHash($industryCode, $source, $recordQuarter, $waccRate);

        return [
            'industry_code' => $industryCode,
            'industry_label' => (string) ($record['industry_label'] ?? Str::headline($industryCode)),
            'wacc_rate' => $waccRate,
            'cost_of_equity' => $this->rate($record['cost_of_equity'] ?? null),
            'cost_of_debt' => $this->rate($record['cost_of_debt'] ?? null),
            'equity_weight' => $this->weight($record['equity_weight'] ?? null),
            'debt_weight' => $this->weight($record['debt_weight'] ?? null),
            'source' => $source,
            'source_badge' => (string) ($record['source_badge'] ?? 'unknown'),
            'degraded' => (bool) ($record['degraded'] ?? false),
            'correlation_id' => $this->uuidOrNull($record['correlation_id'] ?? null),
            'quarter' => $recordQuarter,
            'fetched_at' => $fetchedAt,
            'superseded_at' => null,
            'record_hash' => $hash,
            'payload' => $record['payload'] ?? $record,
        ];
    }

    private function rate(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $rate = (float) $value;

        return $rate > 0 && $rate < 1 ? round($rate, 6) : null;
    }

    private function weight(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $weight = (float) $value;

        return $weight >= 0 && $weight <= 1 ? round($weight, 6) : null;
    }

    private function quarter(?string $quarter, CarbonInterface $date, ?string $fallback = null): string
    {
        $quarter = strtoupper(trim((string) $quarter));
        if ($quarter !== '') {
            return $quarter;
        }

        if ($fallback !== null && $fallback !== '') {
            return $fallback;
        }

        return sprintf('%dQ%d', $date->year, (int) ceil($date->month / 3));
    }

    private function recordHash(string $industryCode, string $source, string $quarter, float $waccRate): string
    {
        return hash('sha256', implode('|', [$industryCode, $source, $quarter, number_format($waccRate, 6, '.', '')]));
    }

    private function uuidOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? $value : '';

        return preg_match('/^[0-9a-fA-F-]{36}$/', $value) === 1 ? $value : null;
    }
}
