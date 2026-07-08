<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AiUsageEvent;
use App\Models\User;
use App\Services\Ai\AdvisorAiNotice;
use App\Services\Ai\Claude\AnthropicClaudeClient;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Tests\TestCase;

final class IntegrityEnforcedTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_anthropic_key_returns_degraded_high_uncertainty_response(): void
    {
        Config::set('services.anthropic.key', '');
        Cache::forget(AdvisorAiNotice::CACHE_KEY);
        Log::spy();

        $response = app(AiClient::class)->summarise(new PromptEnvelope(
            id: 'summarise.smoke',
            version: '2026-05-wo04',
            task: 'summarise',
            body: 'Summarise the supplied material.',
            input: ['text' => 'Example input.'],
        ));

        $this->assertSame(FakeAiClient::DEGRADED_TEXT, $response->text);
        $this->assertSame(Uncertainty::High, $response->uncertainty);
        $this->assertSame('fake-ai-client', $response->model);
        $this->assertSame('AI provider is forced into governed degraded mode.', $response->metadata['unavailable_reason'] ?? null);
        $this->assertSame(FakeAiClient::DEGRADED_TEXT, Cache::get(AdvisorAiNotice::CACHE_KEY)['message'] ?? null);

        Log::shouldHaveReceived('info')
            ->with('ai.bias_assessed', \Mockery::type('array'))
            ->once();
    }

    public function test_degraded_ai_notice_is_shared_with_authenticated_inertia_pages(): void
    {
        Cache::put(AdvisorAiNotice::CACHE_KEY, [
            'message' => FakeAiClient::DEGRADED_TEXT,
            'reason' => 'Anthropic API key is not configured.',
            'prompt_id' => 'summarise.smoke',
            'recorded_at' => now()->toIso8601String(),
        ], now()->addMinute());

        $request = Request::create('/dashboard');
        $request->setUserResolver(fn () => new User([
            'name' => 'Advisor',
            'email' => 'advisor@example.test',
        ]));

        $shared = app(HandleInertiaRequests::class)->share($request);
        $notice = $shared['aiNotice'];

        $this->assertIsCallable($notice);
        $this->assertSame(FakeAiClient::DEGRADED_TEXT, $notice()['message']);
    }

    public function test_degraded_ai_notice_clears_after_later_successful_ai_usage(): void
    {
        Cache::put(AdvisorAiNotice::CACHE_KEY, [
            'message' => FakeAiClient::DEGRADED_TEXT,
            'reason' => 'Anthropic API request failed with status 400.',
            'prompt_id' => 'entrepreneur.idea_validation',
            'recorded_at' => now()->subMinutes(2)->toIso8601String(),
        ], now()->addMinute());

        AiUsageEvent::query()->create([
            'provider' => 'anthropic',
            'task' => 'analyse',
            'model' => 'claude-sonnet-4-6',
            'input_tokens' => 1_579,
            'output_tokens' => 1_797,
            'estimated_cost_usd' => 0.0317,
            'occurred_at' => now()->subMinute(),
        ]);

        $request = Request::create('/advisor/entrepreneurs/example');
        $request->setUserResolver(fn () => new User([
            'name' => 'Advisor',
            'email' => 'advisor@example.test',
        ]));

        $shared = app(HandleInertiaRequests::class)->share($request);
        $notice = $shared['aiNotice'];

        $this->assertIsCallable($notice);
        $this->assertNull($notice());
        $this->assertNull(Cache::get(AdvisorAiNotice::CACHE_KEY));
    }

    public function test_anthropic_client_uses_resilient_http_instead_of_raw_http_facade(): void
    {
        $reflection = new ReflectionClass(AnthropicClaudeClient::class);
        $constructor = $reflection->getConstructor();
        $parameterTypes = collect($constructor?->getParameters() ?? [])
            ->map(fn (\ReflectionParameter $parameter): ?string => $parameter->getType()?->__toString())
            ->filter()
            ->values()
            ->all();
        $contents = file_get_contents($reflection->getFileName()) ?: '';

        $this->assertContains(ResilientHttp::class, $parameterTypes);
        $this->assertStringNotContainsString('Http::', $contents);
    }
}
