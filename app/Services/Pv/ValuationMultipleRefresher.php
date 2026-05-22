<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Models\ValuationMultiple;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\Mbie\Contracts\MbieClient;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ValuationMultipleRefresher
{
    public const LAYER_ID = 13;

    public function __construct(
        private readonly MbieClient $mbie,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * @return array{run: LearningLayerRun, multiples_refreshed: int, rows_superseded: int, candidates_created: int}
     */
    public function refresh(?CarbonInterface $fetchedAt = null, ?string $quarter = null): array
    {
        $fetchedAt ??= now();
        $quarter = $this->quarter($quarter, $fetchedAt);
        $this->context->apply('system', []);

        $records = $this->mbie->valuationMultiples();

        return DB::transaction(function () use ($records, $fetchedAt, $quarter): array {
            $created = collect();
            $supersededRows = 0;

            foreach ($records as $record) {
                if (! is_array($record)) {
                    continue;
                }

                $normalized = $this->normalize($record, $fetchedAt, $quarter);
                if ($normalized === null) {
                    continue;
                }

                $existing = ValuationMultiple::query()
                    ->where('record_hash', $normalized['record_hash'])
                    ->first();

                if ($existing instanceof ValuationMultiple) {
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

                $supersededRows += ValuationMultiple::query()
                    ->where('industry_code', $normalized['industry_code'])
                    ->where('metric', $normalized['metric'])
                    ->where('source', $normalized['source'])
                    ->whereNull('superseded_at')
                    ->update(['superseded_at' => $fetchedAt, 'updated_at' => now()]);

                $created->push(ValuationMultiple::query()->create($normalized));
            }

            $candidate = $created->isNotEmpty()
                ? $this->createCandidate($created->all(), $quarter, $fetchedAt, $supersededRows)
                : null;

            $run = LearningLayerRun::query()->create([
                'layer_id' => self::LAYER_ID,
                'ran_at' => now(),
                'candidates_created' => $candidate instanceof LearningUpdate ? 1 : 0,
                'window' => [
                    'quarter' => $quarter,
                    'fetched_at' => $fetchedAt->toIso8601String(),
                    'multiples_refreshed' => $created->count(),
                    'rows_superseded' => $supersededRows,
                ],
                'status' => LearningLayerRun::STATUS_COMPLETED,
            ]);

            $this->audit->record(
                action: 'valuation_multiples.refreshed',
                subject: $run,
                after: [
                    'layer_id' => self::LAYER_ID,
                    'quarter' => $quarter,
                    'multiples_refreshed' => $created->count(),
                    'rows_superseded' => $supersededRows,
                    'candidates_created' => $candidate instanceof LearningUpdate ? 1 : 0,
                ],
            );

            return [
                'run' => $run,
                'multiples_refreshed' => $created->count(),
                'rows_superseded' => $supersededRows,
                'candidates_created' => $candidate instanceof LearningUpdate ? 1 : 0,
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
        $metric = strtolower(trim((string) ($record['metric'] ?? '')));
        $source = strtolower(trim((string) ($record['source'] ?? ValuationMultiple::SOURCE_MBIE)));

        if ($industryCode === '' || ! in_array($metric, [ValuationMultiple::METRIC_EBITDA, ValuationMultiple::METRIC_SDE], true)) {
            return null;
        }

        if (! in_array($source, [ValuationMultiple::SOURCE_MBIE, ValuationMultiple::SOURCE_NZ_BUSINESS_BROKERS], true)) {
            return null;
        }

        $low = (float) ($record['multiple_low'] ?? 0);
        $mid = (float) ($record['multiple_mid'] ?? 0);
        $high = (float) ($record['multiple_high'] ?? 0);

        if ($low <= 0 || $mid <= 0 || $high <= 0 || $low > $mid || $mid > $high) {
            return null;
        }

        $recordQuarter = $this->quarter(is_string($record['quarter'] ?? null) ? $record['quarter'] : null, $fetchedAt, $quarter);
        $hash = $this->recordHash($industryCode, $metric, $source, $recordQuarter, $low, $mid, $high);

        return [
            'industry_code' => $industryCode,
            'industry_label' => (string) ($record['industry_label'] ?? Str::headline($industryCode)),
            'metric' => $metric,
            'multiple_low' => $low,
            'multiple_mid' => $mid,
            'multiple_high' => $high,
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

    /**
     * @param  array<int, ValuationMultiple>  $multiples
     */
    private function createCandidate(
        array $multiples,
        string $quarter,
        CarbonInterface $fetchedAt,
        int $supersededRows,
    ): ?LearningUpdate {
        $recordHashes = collect($multiples)
            ->map(fn (ValuationMultiple $multiple): string => $multiple->record_hash)
            ->sort()
            ->values()
            ->all();
        $quarters = collect($multiples)
            ->pluck('quarter')
            ->unique()
            ->sort()
            ->values()
            ->all();
        $sourceQuarter = count($quarters) === 1 ? (string) $quarters[0] : $quarter;

        $signalKey = hash('sha256', implode('|', [
            'valuation_multiple_refresh',
            $sourceQuarter,
            ...$recordHashes,
        ]));

        if ($this->candidateExists($signalKey)) {
            return null;
        }

        $sources = collect($multiples)->pluck('source')->unique()->sort()->values()->all();
        $industries = collect($multiples)->pluck('industry_code')->unique()->sort()->values()->all();

        return LearningUpdate::query()->create([
            'layer_id' => self::LAYER_ID,
            'source' => [
                'type' => 'valuation_multiple_refresh',
                'signal_key' => $signalKey,
                'quarter' => $sourceQuarter,
                'sources' => $sources,
            ],
            'summary' => sprintf(
                'Valuation multiple refresh for %s imported %d active reference row(s); review PV multiple assumptions.',
                $sourceQuarter,
                count($multiples),
            ),
            'proposed_change' => [
                'action' => 'review_valuation_multiple_assumptions',
                'automatic_application' => false,
            ],
            'impact_scope' => [
                'future_work_orders' => ['WO-41'],
                'analysis_contexts' => ['business_valuation', 'pv_engine'],
            ],
            'clients_affected' => 0,
            'magnitude' => $supersededRows > 0 ? 'medium' : 'low',
            'confidence' => 0.75,
            'evidence' => [
                'quarter' => $quarter,
                'source_quarter' => $sourceQuarter,
                'quarters' => $quarters,
                'fetched_at' => $fetchedAt->toIso8601String(),
                'multiples_created' => count($multiples),
                'rows_superseded' => $supersededRows,
                'sources' => $sources,
                'industries' => $industries,
                'valuation_multiple_ids' => collect($multiples)->pluck('id')->values()->all(),
                'record_hashes' => $recordHashes,
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }

    private function candidateExists(string $signalKey): bool
    {
        return LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->where('status', LearningUpdate::STATUS_DETECTED)
            ->where('source->type', 'valuation_multiple_refresh')
            ->where('source->signal_key', $signalKey)
            ->exists();
    }

    private function recordHash(
        string $industryCode,
        string $metric,
        string $source,
        string $quarter,
        float $low,
        float $mid,
        float $high,
    ): string {
        return hash('sha256', implode('|', [
            'valuation_multiple',
            $industryCode,
            $metric,
            $source,
            $quarter,
            number_format($low, 2, '.', ''),
            number_format($mid, 2, '.', ''),
            number_format($high, 2, '.', ''),
        ]));
    }

    private function quarter(?string $quarter, CarbonInterface $fetchedAt, ?string $fallback = null): string
    {
        if (is_string($quarter) && preg_match('/^\d{4}Q[1-4]$/', strtoupper($quarter)) === 1) {
            return strtoupper($quarter);
        }

        if ($fallback !== null) {
            return $fallback;
        }

        $date = $fetchedAt instanceof Carbon ? $fetchedAt : Carbon::instance($fetchedAt);

        return sprintf('%dQ%d', $date->year, $date->quarter);
    }

    private function uuidOrNull(mixed $value): ?string
    {
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }
}
