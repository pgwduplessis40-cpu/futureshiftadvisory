<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Models\ValuationMultiple;

final class ValuationMultipleProvider
{
    public function lookup(
        string $industryCode,
        string $metric = ValuationMultiple::METRIC_EBITDA,
        ?string $source = null,
    ): ?ValuationMultiple {
        return ValuationMultiple::query()
            ->where('industry_code', $this->industryCode($industryCode))
            ->where('metric', $metric)
            ->whereNull('superseded_at')
            ->when($source !== null, fn ($query) => $query->where('source', $source))
            ->orderByDesc('fetched_at')
            ->orderByRaw('CASE WHEN source = ? THEN 0 WHEN source = ? THEN 1 ELSE 2 END', [
                ValuationMultiple::SOURCE_NZ_BUSINESS_BROKERS,
                ValuationMultiple::SOURCE_MBIE,
            ])
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function rangeFor(
        string $industryCode,
        string $metric = ValuationMultiple::METRIC_EBITDA,
        ?string $source = null,
    ): ?array {
        $multiple = $this->lookup($industryCode, $metric, $source);

        if (! $multiple instanceof ValuationMultiple) {
            return null;
        }

        return [
            'industry_code' => $multiple->industry_code,
            'industry_label' => $multiple->industry_label,
            'metric' => $multiple->metric,
            'multiple_low' => $multiple->multiple_low,
            'multiple_mid' => $multiple->multiple_mid,
            'multiple_high' => $multiple->multiple_high,
            'source' => $multiple->source,
            'source_badge' => $multiple->source_badge,
            'degraded' => $multiple->degraded,
            'quarter' => $multiple->quarter,
            'source_reference' => "valuation_multiple:{$multiple->id}",
        ];
    }

    private function industryCode(string $industryCode): string
    {
        return strtoupper(trim($industryCode));
    }
}
