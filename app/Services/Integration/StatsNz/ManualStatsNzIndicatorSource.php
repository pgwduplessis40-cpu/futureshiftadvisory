<?php

declare(strict_types=1);

namespace App\Services\Integration\StatsNz;

use App\Models\EconomicIndicator;
use Illuminate\Support\Facades\Schema;

final class ManualStatsNzIndicatorSource
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function indicators(): array
    {
        if (! Schema::hasTable('economic_indicators')) {
            return [];
        }

        return EconomicIndicator::query()
            ->whereIn('indicator', [
                EconomicIndicator::CPI_ANNUAL,
                EconomicIndicator::GDP_QUARTERLY,
                EconomicIndicator::UNEMPLOYMENT_RATE,
            ])
            ->where('source_badge', 'manual_admin')
            ->orderBy('indicator')
            ->orderByDesc('period_date')
            ->orderByDesc('fetched_at')
            ->get()
            ->unique('indicator')
            ->values()
            ->map(fn (EconomicIndicator $indicator): array => [
                'indicator' => $indicator->indicator,
                'label' => $indicator->label,
                'value' => $indicator->value,
                'unit' => $indicator->unit,
                'period_date' => $indicator->period_date?->toDateString(),
                'source' => $indicator->source,
                'source_badge' => $indicator->source_badge,
                'degraded' => $indicator->degraded,
                'correlation_id' => $indicator->correlation_id,
                'payload' => $indicator->payload,
            ])
            ->all();
    }
}
