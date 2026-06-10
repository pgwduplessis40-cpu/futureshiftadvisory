<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Arr;

final class AiCostEstimator
{
    public function estimateUsd(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cacheCreationInputTokens = 0,
        int $cacheReadInputTokens = 0,
    ): float {
        $rates = $this->rates($provider, $model);
        $uncachedInputTokens = max(0, $inputTokens - $cacheCreationInputTokens - $cacheReadInputTokens);

        return round(
            ($uncachedInputTokens / 1_000_000) * (float) ($rates['input_usd_per_mtok'] ?? 0)
            + ($outputTokens / 1_000_000) * (float) ($rates['output_usd_per_mtok'] ?? 0)
            + ($cacheCreationInputTokens / 1_000_000) * (float) ($rates['cache_write_5m_usd_per_mtok'] ?? 0)
            + ($cacheReadInputTokens / 1_000_000) * (float) ($rates['cache_read_usd_per_mtok'] ?? 0),
            8,
        );
    }

    /**
     * @return array<string, float|int|string|null>
     */
    private function rates(string $provider, string $model): array
    {
        $providerPricing = config("ai.pricing.{$provider}", []);
        if (! is_array($providerPricing)) {
            return [];
        }

        $models = Arr::get($providerPricing, 'models', []);
        if (! is_array($models)) {
            $models = [];
        }

        $normalisedModel = str_replace('.', '-', strtolower($model));
        foreach ($models as $key => $rates) {
            if (is_string($key) && str_contains($normalisedModel, str_replace('.', '-', strtolower($key))) && is_array($rates)) {
                return $rates;
            }
        }

        $default = Arr::get($providerPricing, 'default', []);

        return is_array($default) ? $default : [];
    }
}
