<?php

declare(strict_types=1);

namespace App\Services\ReferenceData;

use App\Models\EconomicIndicator;
use App\Models\IndustryWaccData;
use App\Models\LearningUpdate;
use App\Models\ReferenceDataEntry;
use App\Models\ValuationMultiple;
use Carbon\CarbonInterface;

final class ReferenceDataProjector
{
    /**
     * @return array<string, mixed>|null
     */
    public function projectIfReferenceData(LearningUpdate $update, CarbonInterface $implementedAt): ?array
    {
        if (data_get($update->source, 'type') !== 'manual_reference_data') {
            return null;
        }

        $entry = ReferenceDataEntry::query()
            ->where('learning_update_id', $update->getKey())
            ->first();

        if (! $entry instanceof ReferenceDataEntry) {
            return null;
        }

        return match ($entry->dataset) {
            ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR => $this->projectEconomic($entry, $implementedAt),
            ReferenceDataEntry::DATASET_VALUATION_MULTIPLE => $this->projectValuation($entry, $implementedAt),
            ReferenceDataEntry::DATASET_INDUSTRY_WACC => $this->projectWacc($entry, $implementedAt),
            ReferenceDataEntry::DATASET_CPB_BENCHMARK => [
                'target_type' => 'learning_update',
                'target_id' => (string) $update->getKey(),
                'projected' => false,
                'dataset' => $entry->dataset,
            ],
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function projectEconomic(ReferenceDataEntry $entry, CarbonInterface $implementedAt): array
    {
        $payload = $entry->payload;

        /** @var EconomicIndicator $indicator */
        $indicator = EconomicIndicator::query()->updateOrCreate(
            [
                'indicator' => (string) $payload['indicator'],
                'period_date' => (string) $payload['period_date'],
                'source' => (string) $entry->source,
            ],
            [
                'label' => (string) $payload['label'],
                'value' => (float) $payload['value'],
                'unit' => (string) $payload['unit'],
                'source_badge' => 'manual_admin',
                'degraded' => false,
                'correlation_id' => null,
                'fetched_at' => $implementedAt,
                'payload' => [
                    ...$payload,
                    'reference_data_entry_id' => $entry->getKey(),
                ],
            ],
        );

        return [
            'target_type' => EconomicIndicator::class,
            'target_id' => (string) $indicator->getKey(),
            'projected' => true,
            'dataset' => $entry->dataset,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectValuation(ReferenceDataEntry $entry, CarbonInterface $implementedAt): array
    {
        $payload = $entry->payload;
        $hash = $this->valuationHash($payload, $entry->source);

        $existing = ValuationMultiple::query()->where('record_hash', $hash)->first();
        if (! $existing instanceof ValuationMultiple) {
            ValuationMultiple::query()
                ->where('industry_code', (string) $payload['industry_code'])
                ->where('metric', (string) $payload['metric'])
                ->where('source', (string) $entry->source)
                ->whereNull('superseded_at')
                ->update(['superseded_at' => $implementedAt, 'updated_at' => now()]);

            $existing = ValuationMultiple::query()->create([
                'industry_code' => (string) $payload['industry_code'],
                'industry_label' => (string) $payload['industry_label'],
                'metric' => (string) $payload['metric'],
                'multiple_low' => (float) $payload['multiple_low'],
                'multiple_mid' => (float) $payload['multiple_mid'],
                'multiple_high' => (float) $payload['multiple_high'],
                'source' => (string) $entry->source,
                'source_badge' => 'manual_admin',
                'degraded' => false,
                'correlation_id' => null,
                'quarter' => (string) $payload['quarter'],
                'fetched_at' => $implementedAt,
                'superseded_at' => null,
                'record_hash' => $hash,
                'payload' => [
                    ...$payload,
                    'reference_data_entry_id' => $entry->getKey(),
                ],
            ]);
        }

        return [
            'target_type' => ValuationMultiple::class,
            'target_id' => (string) $existing->getKey(),
            'projected' => true,
            'dataset' => $entry->dataset,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectWacc(ReferenceDataEntry $entry, CarbonInterface $implementedAt): array
    {
        $payload = $entry->payload;
        $hash = $this->waccHash($payload, $entry->source);

        $existing = IndustryWaccData::query()->where('record_hash', $hash)->first();
        if (! $existing instanceof IndustryWaccData) {
            IndustryWaccData::query()
                ->where('industry_code', (string) $payload['industry_code'])
                ->where('source', (string) $entry->source)
                ->whereNull('superseded_at')
                ->update(['superseded_at' => $implementedAt, 'updated_at' => now()]);

            $existing = IndustryWaccData::query()->create([
                'industry_code' => (string) $payload['industry_code'],
                'industry_label' => (string) $payload['industry_label'],
                'wacc_rate' => (float) $payload['wacc_rate'],
                'cost_of_equity' => $this->nullableFloat($payload['cost_of_equity'] ?? null),
                'cost_of_debt' => $this->nullableFloat($payload['cost_of_debt'] ?? null),
                'equity_weight' => $this->nullableFloat($payload['equity_weight'] ?? null),
                'debt_weight' => $this->nullableFloat($payload['debt_weight'] ?? null),
                'source' => (string) $entry->source,
                'source_badge' => 'manual_admin',
                'degraded' => false,
                'correlation_id' => null,
                'quarter' => (string) $payload['quarter'],
                'fetched_at' => $implementedAt,
                'superseded_at' => null,
                'record_hash' => $hash,
                'payload' => [
                    ...$payload,
                    'reference_data_entry_id' => $entry->getKey(),
                ],
            ]);
        }

        return [
            'target_type' => IndustryWaccData::class,
            'target_id' => (string) $existing->getKey(),
            'projected' => true,
            'dataset' => $entry->dataset,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function valuationHash(array $payload, string $source): string
    {
        return hash('sha256', implode('|', [
            'valuation_multiple',
            (string) $payload['industry_code'],
            (string) $payload['metric'],
            $source,
            (string) $payload['quarter'],
            number_format((float) $payload['multiple_low'], 2, '.', ''),
            number_format((float) $payload['multiple_mid'], 2, '.', ''),
            number_format((float) $payload['multiple_high'], 2, '.', ''),
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function waccHash(array $payload, string $source): string
    {
        return hash('sha256', implode('|', [
            (string) $payload['industry_code'],
            $source,
            (string) $payload['quarter'],
            number_format((float) $payload['wacc_rate'], 6, '.', ''),
        ]));
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 6) : null;
    }
}
