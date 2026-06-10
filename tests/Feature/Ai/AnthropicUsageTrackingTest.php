<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\AiUsageEvent;
use App\Services\Ai\Claude\AnthropicClaudeClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AnthropicUsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_anthropic_response_records_usage_and_estimated_cost(): void
    {
        Config::set('services.anthropic.key', 'test-anthropic-key');
        Config::set('services.anthropic.model', 'claude-sonnet-4-6');
        Config::set('services.anthropic.endpoint', 'https://api.anthropic.com/v1/messages');

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'text' => 'Structured result.',
                            'attributions' => [
                                ['claim' => 'Claim', 'source_reference' => 'source-1'],
                            ],
                            'uncertainty' => 'low',
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
                'usage' => [
                    'input_tokens' => 1_000,
                    'output_tokens' => 200,
                ],
            ]),
        ]);

        $response = app(AnthropicClaudeClient::class)->summarise(new PromptEnvelope(
            id: 'summarise.usage-test',
            version: '2026-06-usage',
            task: 'summarise',
            body: 'Summarise the supplied material.',
            input: ['text' => 'Example input.'],
        ));

        $this->assertSame('Structured result.', $response->text);
        $this->assertSame(Uncertainty::Low, $response->uncertainty);
        $this->assertSame(1_000, $response->tokensIn);
        $this->assertSame(200, $response->tokensOut);

        $event = AiUsageEvent::query()->firstOrFail();
        $this->assertSame('anthropic', $event->provider);
        $this->assertSame('summarise', $event->task);
        $this->assertSame('claude-sonnet-4-6', $event->model);
        $this->assertSame(1_000, $event->input_tokens);
        $this->assertSame(200, $event->output_tokens);
        $this->assertEqualsWithDelta(0.006, $event->estimated_cost_usd, 0.000001);
    }
}
