<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Services\Ai\AiCostEstimator;
use Tests\TestCase;

final class AiCostEstimatorTest extends TestCase
{
    public function test_estimates_anthropic_sonnet_input_and_output_cost(): void
    {
        $cost = app(AiCostEstimator::class)->estimateUsd(
            provider: 'anthropic',
            model: 'claude-sonnet-4-6',
            inputTokens: 10_000,
            outputTokens: 1_000,
        );

        $this->assertSame(0.045, $cost);
    }

    public function test_estimates_cache_token_rates_separately(): void
    {
        $cost = app(AiCostEstimator::class)->estimateUsd(
            provider: 'anthropic',
            model: 'claude-sonnet-4-6',
            inputTokens: 10_000,
            outputTokens: 1_000,
            cacheCreationInputTokens: 5_000,
            cacheReadInputTokens: 1_000,
        );

        $this->assertSame(0.04605, $cost);
    }
}
