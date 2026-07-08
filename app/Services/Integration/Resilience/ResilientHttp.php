<?php

declare(strict_types=1);

namespace App\Services\Integration\Resilience;

use App\Models\IntegrationCall;
use App\Services\Audit\Redactor;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class ResilientHttp
{
    public function __construct(
        private readonly RetryPolicy $retryPolicy,
        private readonly CircuitBreaker $breaker,
        private readonly HealthRecorder $recorder,
        private readonly Redactor $redactor,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function get(
        string $service,
        string $endpoint,
        array $query = [],
        ?string $cacheKey = null,
        ?callable $fallback = null,
        array $headers = [],
        ?int $timeoutSeconds = null,
    ): IntegrationResult {
        $options = [];
        if ($query !== []) {
            $options['query'] = $query;
        }
        if ($headers !== []) {
            $options['headers'] = $headers;
        }
        $this->applyTimeout($options, $timeoutSeconds);

        return $this->request(
            method: 'GET',
            service: $service,
            endpoint: $endpoint,
            options: $options,
            cacheKey: $cacheKey,
            fallback: $fallback,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function post(
        string $service,
        string $endpoint,
        array $payload = [],
        ?string $cacheKey = null,
        ?callable $fallback = null,
        array $headers = [],
        ?int $timeoutSeconds = null,
    ): IntegrationResult {
        $options = [];
        if ($payload !== []) {
            $options['json'] = $payload;
        }
        if ($headers !== []) {
            $options['headers'] = $headers;
        }
        $this->applyTimeout($options, $timeoutSeconds);

        return $this->request(
            method: 'POST',
            service: $service,
            endpoint: $endpoint,
            options: $options,
            cacheKey: $cacheKey,
            fallback: $fallback,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function request(
        string $method,
        string $service,
        string $endpoint,
        array $options = [],
        ?string $cacheKey = null,
        ?callable $fallback = null,
    ): IntegrationResult {
        $correlationId = (string) Str::uuid();

        if ($this->breaker->isOpen($service)) {
            return $this->cachedOrFallback(
                service: $service,
                endpoint: $endpoint,
                correlationId: $correlationId,
                cacheKey: $cacheKey,
                fallback: $fallback,
                reason: 'circuit_open',
                attempt: 0,
            );
        }

        for ($attempt = 1; $attempt <= $this->retryPolicy->attempts; $attempt++) {
            $started = microtime(true);

            try {
                $response = Http::send(strtoupper($method), $endpoint, $options);
                $latencyMs = $this->latencyMs($started);

                if ($response->successful()) {
                    $this->recorder->record(
                        service: $service,
                        endpoint: $endpoint,
                        status: IntegrationCall::STATUS_SUCCESS,
                        attempt: $attempt,
                        latencyMs: $latencyMs,
                        correlationId: $correlationId,
                    );
                    $this->breaker->recordSuccess($service);
                    $this->cacheResponse($cacheKey, $response);

                    return IntegrationResult::fromResponse($response, $correlationId);
                }

                $errorPayload = [
                    'http_status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                ];

                if ($this->retryPolicy->shouldRetryStatus($response->status(), $attempt)) {
                    $this->recorder->record(
                        service: $service,
                        endpoint: $endpoint,
                        status: IntegrationCall::STATUS_RETRY,
                        attempt: $attempt,
                        latencyMs: $latencyMs,
                        errorPayload: $errorPayload,
                        correlationId: $correlationId,
                    );
                    $this->pauseBeforeRetry($attempt);

                    continue;
                }

                return $this->finalFailure(
                    service: $service,
                    endpoint: $endpoint,
                    correlationId: $correlationId,
                    cacheKey: $cacheKey,
                    fallback: $fallback,
                    attempt: $attempt,
                    latencyMs: $latencyMs,
                    reason: 'http_failure',
                    errorPayload: $errorPayload,
                );
            } catch (Throwable $e) {
                $latencyMs = $this->latencyMs($started);
                $errorPayload = [
                    'exception' => $e::class,
                    'message' => Str::limit($e->getMessage(), 500),
                ];

                if ($this->retryPolicy->shouldRetryException($attempt)) {
                    $this->recorder->record(
                        service: $service,
                        endpoint: $endpoint,
                        status: IntegrationCall::STATUS_RETRY,
                        attempt: $attempt,
                        latencyMs: $latencyMs,
                        errorPayload: $errorPayload,
                        correlationId: $correlationId,
                    );
                    $this->pauseBeforeRetry($attempt);

                    continue;
                }

                return $this->finalFailure(
                    service: $service,
                    endpoint: $endpoint,
                    correlationId: $correlationId,
                    cacheKey: $cacheKey,
                    fallback: $fallback,
                    attempt: $attempt,
                    latencyMs: $latencyMs,
                    reason: 'exception',
                    errorPayload: $errorPayload,
                );
            }
        }

        return $this->cachedOrFallback(
            service: $service,
            endpoint: $endpoint,
            correlationId: $correlationId,
            cacheKey: $cacheKey,
            fallback: $fallback,
            reason: 'exhausted',
            attempt: $this->retryPolicy->attempts,
        );
    }

    /**
     * @param  array<string, mixed>  $errorPayload
     */
    private function finalFailure(
        string $service,
        string $endpoint,
        string $correlationId,
        ?string $cacheKey,
        ?callable $fallback,
        int $attempt,
        ?int $latencyMs,
        string $reason,
        array $errorPayload,
    ): IntegrationResult {
        $this->recorder->record(
            service: $service,
            endpoint: $endpoint,
            status: IntegrationCall::STATUS_FAILURE,
            attempt: $attempt,
            latencyMs: $latencyMs,
            errorPayload: ['reason' => $reason, ...$errorPayload],
            correlationId: $correlationId,
        );
        $this->breaker->recordFailure($service);

        return $this->cachedOrFallback(
            service: $service,
            endpoint: $endpoint,
            correlationId: $correlationId,
            cacheKey: $cacheKey,
            fallback: $fallback,
            reason: $reason,
            attempt: $attempt,
            errorPayload: $errorPayload,
        );
    }

    /**
     * @param  array<string, mixed>  $errorPayload
     */
    private function cachedOrFallback(
        string $service,
        string $endpoint,
        string $correlationId,
        ?string $cacheKey,
        ?callable $fallback,
        string $reason,
        int $attempt,
        array $errorPayload = [],
    ): IntegrationResult {
        if ($cacheKey !== null && Cache::has($cacheKey)) {
            $this->recorder->record(
                service: $service,
                endpoint: $endpoint,
                status: IntegrationCall::STATUS_CACHED,
                attempt: $attempt,
                errorPayload: ['reason' => $reason],
                correlationId: $correlationId,
            );

            return IntegrationResult::cached(Cache::get($cacheKey), $correlationId);
        }

        $fallbackData = $fallback !== null
            ? $fallback()
            : [
                'degraded' => true,
                'service' => $service,
                'reason' => $reason,
                'error_payload' => $this->redactor->redact($errorPayload),
            ];

        $this->recorder->record(
            service: $service,
            endpoint: $endpoint,
            status: IntegrationCall::STATUS_FALLBACK,
            attempt: $attempt,
            errorPayload: ['reason' => $reason, ...$errorPayload],
            correlationId: $correlationId,
        );

        return IntegrationResult::fallback($fallbackData, $correlationId);
    }

    private function cacheResponse(?string $cacheKey, Response $response): void
    {
        if ($cacheKey === null) {
            return;
        }

        Cache::put($cacheKey, [
            'status_code' => $response->status(),
            'json' => $response->json(),
            'body' => $response->body(),
            'cached_at' => now()->toIso8601String(),
        ], (int) Config::get('integrations.cache.ttl_seconds', 900));
    }

    private function latencyMs(float $started): int
    {
        return (int) max(0, round((microtime(true) - $started) * 1000));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function applyTimeout(array &$options, ?int $timeoutSeconds): void
    {
        if ($timeoutSeconds === null || $timeoutSeconds < 1) {
            return;
        }

        $options['timeout'] = $timeoutSeconds;
        $options['connect_timeout'] = min(10, $timeoutSeconds);
    }

    private function pauseBeforeRetry(int $attempt): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $delay = $this->retryPolicy->delayMsForAttempt($attempt);
        if ($delay > 0) {
            usleep($delay * 1000);
        }
    }
}
