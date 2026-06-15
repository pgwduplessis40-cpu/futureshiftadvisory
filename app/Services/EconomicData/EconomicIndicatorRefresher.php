<?php

declare(strict_types=1);

namespace App\Services\EconomicData;

use App\Models\EconomicIndicator;
use App\Models\ExchangeRate;
use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\Mbie\Contracts\MbieClient;
use App\Services\Integration\Rbnz\Contracts\RbnzClient;
use App\Services\Integration\StatsNz\Contracts\StatsNzClient;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EconomicIndicatorRefresher
{
    public const LAYER_ID = 12;

    public function __construct(
        private readonly RbnzClient $rbnz,
        private readonly MbieClient $mbie,
        private readonly StatsNzClient $statsNz,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * @return array{run: LearningLayerRun, indicators: int, exchange_rates: int, candidates_created: int}
     */
    public function refresh(?CarbonInterface $fetchedAt = null): array
    {
        $fetchedAt ??= now();
        $this->context->apply('system', []);

        $indicators = [
            $this->rbnz->ocr(),
            ...$this->statsNz->indicators(),
            ...$this->mbie->wageRates(),
        ];
        $exchangeRates = $this->rbnz->exchangeRates();

        return DB::transaction(function () use ($fetchedAt, $indicators, $exchangeRates): array {
            $previousOcr = $this->latestIndicator(EconomicIndicator::OCR);
            $currentOcr = $this->firstIndicator($indicators, EconomicIndicator::OCR);
            $candidatesCreated = 0;
            $indicatorCount = 0;
            $exchangeRateCount = 0;

            foreach ($indicators as $indicator) {
                if (! is_array($indicator)) {
                    continue;
                }

                $this->persistIndicator($indicator, $fetchedAt);
                $indicatorCount++;
            }

            foreach ($exchangeRates as $rate) {
                if (! is_array($rate)) {
                    continue;
                }

                $this->persistExchangeRate($rate, $fetchedAt);
                $exchangeRateCount++;
            }

            if ($previousOcr instanceof EconomicIndicator && is_array($currentOcr) && $this->ocrChanged($previousOcr, $currentOcr)) {
                $candidate = $this->createOcrCandidate($previousOcr, $currentOcr, $fetchedAt);
                $candidatesCreated = $candidate instanceof LearningUpdate ? 1 : 0;
            }

            $run = LearningLayerRun::query()->create([
                'layer_id' => self::LAYER_ID,
                'ran_at' => now(),
                'candidates_created' => $candidatesCreated,
                'window' => [
                    'fetched_at' => $fetchedAt->toIso8601String(),
                    'indicators_refreshed' => $indicatorCount,
                    'exchange_rates_refreshed' => $exchangeRateCount,
                ],
                'status' => LearningLayerRun::STATUS_COMPLETED,
            ]);

            $this->audit->record(
                action: 'economic_indicators.refreshed',
                subject: $run,
                after: [
                    'layer_id' => self::LAYER_ID,
                    'indicators_refreshed' => $indicatorCount,
                    'exchange_rates_refreshed' => $exchangeRateCount,
                    'candidates_created' => $candidatesCreated,
                ],
            );

            return [
                'run' => $run,
                'indicators' => $indicatorCount,
                'exchange_rates' => $exchangeRateCount,
                'candidates_created' => $candidatesCreated,
            ];
        });
    }

    private function latestIndicator(string $indicator): ?EconomicIndicator
    {
        return EconomicIndicator::query()
            ->where('indicator', $indicator)
            ->latest('period_date')
            ->latest('fetched_at')
            ->first();
    }

    /**
     * @param  array<int, mixed>  $indicators
     * @return array<string, mixed>|null
     */
    private function firstIndicator(array $indicators, string $indicator): ?array
    {
        foreach ($indicators as $record) {
            if (is_array($record) && (string) ($record['indicator'] ?? '') === $indicator) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function persistIndicator(array $record, CarbonInterface $fetchedAt): EconomicIndicator
    {
        $indicator = (string) ($record['indicator'] ?? '');
        $source = (string) ($record['source'] ?? 'unknown');
        $periodDate = $this->date($record['period_date'] ?? null, $fetchedAt);

        return EconomicIndicator::query()->updateOrCreate(
            [
                'indicator' => $indicator,
                'period_date' => $periodDate->toDateString(),
                'source' => $source,
            ],
            [
                'label' => (string) ($record['label'] ?? Str::headline($indicator)),
                'value' => (float) ($record['value'] ?? 0),
                'unit' => (string) ($record['unit'] ?? 'value'),
                'source_badge' => (string) ($record['source_badge'] ?? 'unknown'),
                'degraded' => (bool) ($record['degraded'] ?? false),
                'correlation_id' => $this->uuidOrNull($record['correlation_id'] ?? null),
                'fetched_at' => $fetchedAt,
                'payload' => $record['payload'] ?? $record,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function persistExchangeRate(array $record, CarbonInterface $fetchedAt): ExchangeRate
    {
        $baseCurrency = strtoupper((string) ($record['base_currency'] ?? 'NZD'));
        $quoteCurrency = strtoupper((string) ($record['quote_currency'] ?? 'USD'));
        $source = (string) ($record['source'] ?? 'unknown');
        $rateDate = $this->date($record['rate_date'] ?? null, $fetchedAt);

        return ExchangeRate::query()->updateOrCreate(
            [
                'base_currency' => $baseCurrency,
                'quote_currency' => $quoteCurrency,
                'rate_date' => $rateDate->toDateString(),
                'source' => $source,
            ],
            [
                'rate' => (float) ($record['rate'] ?? 0),
                'source_badge' => (string) ($record['source_badge'] ?? 'unknown'),
                'degraded' => (bool) ($record['degraded'] ?? false),
                'correlation_id' => $this->uuidOrNull($record['correlation_id'] ?? null),
                'fetched_at' => $fetchedAt,
                'payload' => $record['payload'] ?? $record,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $currentOcr
     */
    private function ocrChanged(EconomicIndicator $previousOcr, array $currentOcr): bool
    {
        $currentPeriod = $this->date($currentOcr['period_date'] ?? null, $previousOcr->period_date ?? now());

        if ($previousOcr->period_date instanceof CarbonInterface && $currentPeriod->lessThan($previousOcr->period_date)) {
            return false;
        }

        if ((bool) ($currentOcr['degraded'] ?? false)) {
            return false;
        }

        return abs($previousOcr->value - (float) ($currentOcr['value'] ?? 0)) >= 0.0001;
    }

    /**
     * @param  array<string, mixed>  $currentOcr
     */
    private function createOcrCandidate(
        EconomicIndicator $previousOcr,
        array $currentOcr,
        CarbonInterface $fetchedAt,
    ): ?LearningUpdate {
        $currentValue = (float) ($currentOcr['value'] ?? 0);
        $currentPeriod = $this->date($currentOcr['period_date'] ?? null, $fetchedAt)->toDateString();
        $source = (string) ($currentOcr['source'] ?? 'rbnz');
        $signalKey = $this->ocrSignalKey($previousOcr->value, $currentValue, $currentPeriod, $source);

        if ($this->ocrCandidateExists($signalKey)) {
            return null;
        }

        $delta = round($currentValue - $previousOcr->value, 4);

        return LearningUpdate::query()->create([
            'layer_id' => self::LAYER_ID,
            'source' => [
                'type' => 'economic_indicator_auto_update',
                'signal_key' => $signalKey,
                'indicator' => EconomicIndicator::OCR,
                'source' => $source,
                'period_date' => $currentPeriod,
            ],
            'summary' => sprintf(
                'OCR changed from %.2f%% to %.2f%%; review PV discount-rate assumptions.',
                $previousOcr->value,
                $currentValue,
            ),
            'proposed_change' => [
                'action' => 'review_pv_discount_rate_assumptions',
                'indicator' => EconomicIndicator::OCR,
                'automatic_application' => false,
            ],
            'impact_scope' => [
                'future_work_orders' => ['WO-40', 'WO-53'],
                'analysis_contexts' => ['pv_engine', 'scenario_planning'],
            ],
            'clients_affected' => 0,
            'magnitude' => abs($delta) >= 0.5 ? 'medium' : 'low',
            'confidence' => 0.8,
            'evidence' => [
                'previous_indicator_id' => $previousOcr->id,
                'previous_value' => $previousOcr->value,
                'previous_period_date' => $previousOcr->period_date?->toDateString(),
                'current_value' => $currentValue,
                'current_period_date' => $currentPeriod,
                'delta' => $delta,
                'source_badge' => $currentOcr['source_badge'] ?? null,
                'fetched_at' => $fetchedAt->toIso8601String(),
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }

    private function ocrCandidateExists(string $signalKey): bool
    {
        return LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->where('status', LearningUpdate::STATUS_DETECTED)
            ->where('source->type', 'economic_indicator_auto_update')
            ->where('source->signal_key', $signalKey)
            ->exists();
    }

    private function ocrSignalKey(float $previousValue, float $currentValue, string $periodDate, string $source): string
    {
        return hash('sha256', implode('|', [
            'economic_indicator_auto_update',
            EconomicIndicator::OCR,
            $source,
            $periodDate,
            number_format($previousValue, 4, '.', ''),
            number_format($currentValue, 4, '.', ''),
        ]));
    }

    private function date(mixed $value, CarbonInterface $fallback): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return Carbon::instance($fallback);
    }

    private function uuidOrNull(mixed $value): ?string
    {
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }
}
