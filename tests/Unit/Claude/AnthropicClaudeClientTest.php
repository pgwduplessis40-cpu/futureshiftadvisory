<?php

declare(strict_types=1);

namespace Tests\Unit\Claude;

use App\Services\Ai\Claude\AnthropicClaudeClient;
use App\Services\Ai\Exceptions\AiIntegrityViolation;
use Illuminate\Support\Facades\Config;
use ReflectionMethod;
use Tests\TestCase;

final class AnthropicClaudeClientTest extends TestCase
{
    public function test_refusal_is_reported_without_persisting_provider_content(): void
    {
        $this->expectExceptionObject(new AiIntegrityViolation('Anthropic declined the requested assessment.'));

        $this->structuredPayload([
            'stop_reason' => 'refusal',
            'content' => [['type' => 'text', 'text' => 'Provider refusal details must not be retained.']],
        ]);
    }

    public function test_output_limit_is_reported_as_an_incomplete_structured_response(): void
    {
        $this->expectExceptionObject(new AiIntegrityViolation(
            'Anthropic reached the output token limit before it could return the required JSON.',
        ));

        $this->structuredPayload([
            'stop_reason' => 'max_tokens',
            'content' => [['type' => 'text', 'text' => '{"text":"Partial response']],
        ]);
    }

    public function test_non_json_content_includes_the_safe_stop_reason(): void
    {
        $this->expectExceptionObject(new AiIntegrityViolation(
            'Anthropic returned non-JSON content despite the structured-output request. (stop reason: end_turn)',
        ));

        $this->structuredPayload([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'A freeform response.']],
        ]);
    }

    public function test_output_token_budget_is_configurable_and_bounded(): void
    {
        Config::set('services.anthropic.max_output_tokens', 4096);
        $this->assertSame(4096, $this->maxOutputTokens());

        Config::set('services.anthropic.max_output_tokens', 100);
        $this->assertSame(1024, $this->maxOutputTokens());

        Config::set('services.anthropic.max_output_tokens', 10_000);
        $this->assertSame(8192, $this->maxOutputTokens());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function structuredPayload(array $payload): array
    {
        $method = new ReflectionMethod(AnthropicClaudeClient::class, 'structuredPayload');

        /** @var array<string, mixed> $result */
        $result = $method->invoke(app(AnthropicClaudeClient::class), $payload);

        return $result;
    }

    private function maxOutputTokens(): int
    {
        $method = new ReflectionMethod(AnthropicClaudeClient::class, 'maxOutputTokens');

        return $method->invoke(app(AnthropicClaudeClient::class));
    }
}
