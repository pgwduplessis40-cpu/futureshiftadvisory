<?php

declare(strict_types=1);

namespace Tests\Feature\Integration\Resilience;

use App\Models\IntegrationCall;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Resilience\RetryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ResilientHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('integrations.retry.attempts', 3);
        Config::set('integrations.retry.base_delay_ms', 0);
        Config::set('integrations.retry.max_delay_ms', 0);
        Config::set('integrations.circuit_breaker.failure_threshold', 5);
        Config::set('integrations.circuit_breaker.window_seconds', 60);
        Config::set('integrations.circuit_breaker.open_seconds', 300);
        app()->forgetInstance(RetryPolicy::class);
        app()->forgetInstance(ResilientHttp::class);
    }

    public function test_flaky_service_retries_twice_then_succeeds_and_logs_each_attempt(): void
    {
        Http::fakeSequence()
            ->push(['temporary' => true], 500)
            ->push(['still_temporary' => true], 502)
            ->push(['ok' => true], 200);

        $result = app(ResilientHttp::class)->get(
            service: 'nzbn',
            endpoint: 'https://api.example.test/nzbn/9429000000000',
        );

        $this->assertTrue($result->successful());
        $this->assertSame(true, $result->json('ok'));
        Http::assertSentCount(3);

        $calls = IntegrationCall::query()
            ->where('service', 'nzbn')
            ->orderBy('attempt')
            ->get();

        $this->assertSame([
            IntegrationCall::STATUS_RETRY,
            IntegrationCall::STATUS_RETRY,
            IntegrationCall::STATUS_SUCCESS,
        ], $calls->pluck('status')->all());
        $this->assertSame([1, 2, 3], $calls->pluck('attempt')->all());
        $this->assertSame(1, $calls->pluck('correlation_id')->unique()->count());
    }

    public function test_open_breaker_short_circuits_to_cached_value_without_network_hit(): void
    {
        Config::set('integrations.retry.attempts', 1);
        app()->forgetInstance(RetryPolicy::class);
        app()->forgetInstance(ResilientHttp::class);

        Http::fake(fn () => Http::response(['down' => true], 500));

        for ($i = 0; $i < 5; $i++) {
            app(ResilientHttp::class)->get(
                service: 'companies-office',
                endpoint: 'https://api.example.test/companies',
                fallback: fn () => ['degraded' => true],
            );
        }

        Http::assertSentCount(5);

        Cache::put('companies-office:last-good', [
            'status_code' => 200,
            'json' => ['cached' => true],
            'body' => '{"cached":true}',
        ]);

        $result = app(ResilientHttp::class)->get(
            service: 'companies-office',
            endpoint: 'https://api.example.test/companies',
            cacheKey: 'companies-office:last-good',
            fallback: fn () => ['degraded' => true],
        );

        Http::assertSentCount(5);
        $this->assertTrue($result->fromCache);
        $this->assertSame(true, $result->json('cached'));

        $this->assertDatabaseHas('integration_calls', [
            'service' => 'companies-office',
            'status' => IntegrationCall::STATUS_CACHED,
            'attempt' => 0,
        ]);
    }

    public function test_get_passes_custom_headers_to_provider(): void
    {
        Config::set('integrations.retry.attempts', 1);
        app()->forgetInstance(RetryPolicy::class);
        app()->forgetInstance(ResilientHttp::class);

        Http::fake(fn () => Http::response(['ok' => true], 200));

        $result = app(ResilientHttp::class)->get(
            service: 'nzbn',
            endpoint: 'https://api.business.govt.nz/sandbox/nzbn/v5/entities/9429000000000',
            headers: [
                'Ocp-Apim-Subscription-Key' => 'subscription-key',
                'Accept' => 'application/json',
            ],
        );

        $this->assertTrue($result->successful());
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Ocp-Apim-Subscription-Key', 'subscription-key'));
    }

    public function test_failure_result_includes_provider_error_payload_for_callers(): void
    {
        Config::set('integrations.retry.attempts', 1);
        app()->forgetInstance(RetryPolicy::class);
        app()->forgetInstance(ResilientHttp::class);

        Http::fake(fn () => Http::response([
            'error' => 'invalid_client',
            'error_description' => 'AADSTS7000215: Invalid client secret provided.',
        ], 401));

        $result = app(ResilientHttp::class)->request(
            method: 'POST',
            service: 'microsoft',
            endpoint: 'https://login.microsoftonline.com/example/oauth2/v2.0/token',
            options: ['form_params' => ['client_secret' => 'do-not-log']],
            fallback: null,
        );

        $this->assertTrue($result->fromFallback);
        $this->assertSame('http_failure', $result->json('reason'));
        $this->assertSame(401, $result->json('error_payload.http_status'));
        $this->assertStringContainsString('AADSTS7000215', (string) $result->json('error_payload.body'));
    }
}
