<?php

declare(strict_types=1);

namespace App\Services\Integration\Rbnz;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Rbnz\Contracts\RbnzClient;
use App\Services\Integration\Resilience\IntegrationResult;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

final class LiveRbnzClient implements RbnzClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeRbnzClient $fake,
        private readonly IntegrationActivationResolver $live,
        private readonly IntegrationCredentials $credentials,
    ) {}

    public function ocr(): array
    {
        if (! $this->live->isLive('rbnz')) {
            throw IntegrationDisabledException::forService('rbnz');
        }

        $result = $this->http->get(
            service: 'rbnz',
            endpoint: $this->endpoint('rbnz', 'ocr'),
            query: $this->query('rbnz'),
            cacheKey: 'integration:rbnz:ocr',
            fallback: fn (): array => $this->fake->fallbackOcr(),
        );

        return $this->withTransportMeta($result, $this->fake->fallbackOcr());
    }

    public function exchangeRates(): array
    {
        if (! $this->live->isLive('rbnz')) {
            throw IntegrationDisabledException::forService('rbnz');
        }

        $result = $this->http->get(
            service: 'rbnz',
            endpoint: $this->endpoint('rbnz', 'exchange-rates'),
            query: $this->query('rbnz'),
            cacheKey: 'integration:rbnz:exchange-rates',
            fallback: fn (): array => $this->fake->fallbackExchangeRates(),
        );

        $payload = is_array($result->data) ? $result->data : $this->fake->fallbackExchangeRates();
        $rates = array_is_list($payload) ? $payload : (array) ($payload['exchange_rates'] ?? []);

        return array_values(array_map(
            fn (array $rate): array => $this->withTransportMeta($result, $rate, $rate),
            array_filter($rates, 'is_array'),
        ));
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function withTransportMeta(IntegrationResult $result, array $fallback, ?array $payload = null): array
    {
        $record = $payload ?? (is_array($result->data) ? $result->data : $fallback);

        return [
            ...$record,
            'source' => (string) ($record['source'] ?? 'rbnz'),
            'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
            'degraded' => $result->fromFallback || (bool) ($record['degraded'] ?? false),
            'correlation_id' => $result->correlationId,
        ];
    }

    private function endpoint(string $service, string $path): string
    {
        $apiKey = (string) ($this->credentials->get($service, 'api_key') ?? '');

        return $apiKey === ''
            ? "fsa-disabled://{$service}/missing-api-key/{$path}"
            : rtrim((string) Config::get("integrations.{$service}.base_url"), '/').'/'.$path;
    }

    /**
     * @return array<string, mixed>
     */
    private function query(string $service): array
    {
        $apiKey = (string) ($this->credentials->get($service, 'api_key') ?? '');

        return $apiKey === '' ? [] : ['api_key' => $apiKey];
    }
}
