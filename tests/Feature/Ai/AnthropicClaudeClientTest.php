<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Services\Ai\Claude\AnthropicClaudeClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Resilience\RetryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AnthropicClaudeClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.anthropic.key', 'test-anthropic-key');
        Config::set('services.anthropic.endpoint', 'https://api.anthropic.test/v1/messages');
        Config::set('integrations.retry.attempts', 1);
        Config::set('integrations.retry.base_delay_ms', 0);
        Config::set('integrations.retry.max_delay_ms', 0);
        app()->forgetInstance(RetryPolicy::class);
        app()->forgetInstance(ResilientHttp::class);
        app()->forgetInstance(AnthropicClaudeClient::class);
    }

    public function test_unavailable_exception_uses_upstream_error_payload_from_resilient_fallback(): void
    {
        Http::fake(fn () => Http::response([
            'type' => 'error',
            'error' => [
                'type' => 'overloaded_error',
                'message' => 'Overloaded',
            ],
        ], 529));

        $this->expectException(AiUnavailableException::class);
        $this->expectExceptionMessage('Anthropic API request failed with status 529: Overloaded');

        app(AnthropicClaudeClient::class)->analyse($this->prompt());
    }

    public function test_anthropic_retry_attempts_default_to_one_to_avoid_hidden_spend(): void
    {
        Config::set('integrations.retry.attempts', 3);
        Config::set('services.anthropic.retry_attempts', 1);
        app()->forgetInstance(RetryPolicy::class);
        app()->forgetInstance(ResilientHttp::class);
        app()->forgetInstance(AnthropicClaudeClient::class);

        Http::fake(fn () => Http::response([
            'type' => 'error',
            'error' => [
                'type' => 'api_error',
                'message' => 'Transient failure',
            ],
            'request_id' => 'req_test_123',
        ], 503, ['request-id' => 'req_test_123']));

        try {
            app(AnthropicClaudeClient::class)->analyse($this->prompt());
            $this->fail('Expected Anthropic unavailable exception.');
        } catch (AiUnavailableException $exception) {
            $this->assertStringContainsString('Anthropic API request failed with status 503: Transient failure', $exception->getMessage());
            $this->assertStringContainsString('request id req_test_123', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    private function prompt(): PromptEnvelope
    {
        return new PromptEnvelope(
            id: 'idea_validation.test',
            version: '2026-07-08',
            task: 'Evaluate idea validation.',
            body: 'Return JSON.',
            input: ['idea' => 'A workflow tool for advisors.'],
            dataQualitySummary: [
                'level' => 'test',
                'message' => 'Test input.',
            ],
            sourceReferences: ['test:idea'],
        );
    }
}
