<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiUsageEvent;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class AiUsageRecorder
{
    private ?bool $storeAvailable = null;

    public function __construct(private readonly AiCostEstimator $costs) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $provider,
        string $task,
        string $model,
        ?string $promptVersion,
        ?string $promptHash,
        int $inputTokens,
        int $outputTokens,
        int $cacheCreationInputTokens = 0,
        int $cacheReadInputTokens = 0,
        array $metadata = [],
    ): void {
        if (! $this->storeAvailable()) {
            return;
        }

        try {
            AiUsageEvent::query()->create([
                'provider' => $provider,
                'task' => $task,
                'model' => $model,
                'prompt_version' => $promptVersion,
                'prompt_hash' => $promptHash,
                'input_tokens' => max(0, $inputTokens),
                'output_tokens' => max(0, $outputTokens),
                'cache_creation_input_tokens' => max(0, $cacheCreationInputTokens),
                'cache_read_input_tokens' => max(0, $cacheReadInputTokens),
                'estimated_cost_usd' => $this->costs->estimateUsd(
                    provider: $provider,
                    model: $model,
                    inputTokens: $inputTokens,
                    outputTokens: $outputTokens,
                    cacheCreationInputTokens: $cacheCreationInputTokens,
                    cacheReadInputTokens: $cacheReadInputTokens,
                ),
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function storeAvailable(): bool
    {
        return $this->storeAvailable ??= Schema::hasTable('ai_usage_events');
    }
}
