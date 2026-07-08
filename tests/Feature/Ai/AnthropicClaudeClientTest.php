<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Services\Ai\Claude\AnthropicClaudeClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Resilience\RetryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
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

    public function test_anthropic_timeout_has_sixty_second_floor(): void
    {
        Config::set('services.anthropic.timeout_seconds', 20);

        $client = app(AnthropicClaudeClient::class);
        $method = new ReflectionMethod($client, 'timeoutSeconds');

        $this->assertSame(60, $method->invoke($client));
    }

    public function test_analyse_request_uses_structured_output_schema_for_findings(): void
    {
        Http::fake(fn () => Http::response([
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        'text' => 'Idea validation reviewed.',
                        'attributions' => [],
                        'uncertainty' => 'high',
                        'metadata' => ['findings' => []],
                    ], JSON_THROW_ON_ERROR),
                ],
            ],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
        ]));

        $response = app(AnthropicClaudeClient::class)->analyse($this->prompt());

        $this->assertSame('Idea validation reviewed.', $response->text);
        $this->assertSame(Uncertainty::High, $response->uncertainty);
        Http::assertSent(function (Request $request): bool {
            $schema = data_get($request->data(), 'output_config.format.schema');

            return is_array($schema)
                && data_get($request->data(), 'output_config.format.type') === 'json_schema'
                && data_get($schema, 'properties.metadata.properties.findings.items.properties.lens.enum.0') === 'descriptive'
                && data_get($schema, 'properties.metadata.required.0') === 'findings'
                && $this->allObjectSchemasAreClosed($schema);
        });
    }

    public function test_wrapped_json_text_response_is_still_parsed(): void
    {
        Http::fake(fn () => Http::response([
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Here is the structured response:\n```json\n".json_encode([
                        'text' => 'Wrapped structured result.',
                        'attributions' => [],
                        'uncertainty' => 'low',
                        'metadata' => [],
                    ], JSON_THROW_ON_ERROR)."\n```",
                ],
            ],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
        ]));

        $response = app(AnthropicClaudeClient::class)->summarise($this->prompt());

        $this->assertSame('Wrapped structured result.', $response->text);
        $this->assertSame(Uncertainty::Low, $response->uncertainty);
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

    /**
     * @param  array<mixed>  $schema
     */
    private function allObjectSchemasAreClosed(array $schema): bool
    {
        if (($schema['type'] ?? null) === 'object' && ($schema['additionalProperties'] ?? null) !== false) {
            return false;
        }

        foreach ($schema as $value) {
            if (is_array($value) && ! $this->allObjectSchemasAreClosed($value)) {
                return false;
            }
        }

        return true;
    }
}
