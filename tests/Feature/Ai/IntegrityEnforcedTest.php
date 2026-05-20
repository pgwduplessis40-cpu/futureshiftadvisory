<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Services\Ai\AdvisorAiNotice;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Fake\FakeAiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class IntegrityEnforcedTest extends TestCase
{
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

    public function test_anthropic_http_post_only_exists_inside_anthropic_client(): void
    {
        $root = base_path('app');
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $contents = file_get_contents($path) ?: '';

            if (! str_contains($contents, '->post(') && ! str_contains($contents, 'Http::post(')) {
                continue;
            }

            if (str_ends_with(str_replace('\\', '/', $path), 'app/Services/Ai/Claude/AnthropicClaudeClient.php')) {
                continue;
            }

            $violations[] = $path;
        }

        $this->assertSame([], $violations);
    }
}
