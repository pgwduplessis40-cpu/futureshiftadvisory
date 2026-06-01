<?php

declare(strict_types=1);

namespace App\Services\Integration\Mbie;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Mbie\Contracts\MbieClient;
use App\Services\Integration\Resilience\IntegrationResult;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

final class LiveMbieClient implements MbieClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeMbieClient $fake,
        private readonly IntegrationActivationResolver $live,
        private readonly IntegrationCredentials $credentials,
    ) {}

    public function wageRates(): array
    {
        if (! $this->live->isLive('mbie')) {
            throw IntegrationDisabledException::forService('mbie');
        }

        $result = $this->http->get(
            service: 'mbie',
            endpoint: $this->endpoint(),
            query: $this->query(),
            cacheKey: 'integration:mbie:wage-rates',
            fallback: fn (): array => $this->fake->fallbackWageRates(),
        );

        $payload = is_array($result->data) ? $result->data : $this->fake->fallbackWageRates();
        $rates = array_is_list($payload) ? $payload : (array) ($payload['wage_rates'] ?? []);

        return array_values(array_map(
            fn (array $rate): array => $this->withTransportMeta($result, $rate),
            array_filter($rates, 'is_array'),
        ));
    }

    public function valuationMultiples(): array
    {
        if (! $this->live->isLive('mbie')) {
            throw IntegrationDisabledException::forService('mbie');
        }

        $result = $this->http->get(
            service: 'mbie',
            endpoint: $this->endpoint('valuation-multiples'),
            query: $this->query(),
            cacheKey: 'integration:mbie:valuation-multiples',
            fallback: fn (): array => $this->fake->fallbackValuationMultiples(),
        );

        $payload = is_array($result->data) ? $result->data : $this->fake->fallbackValuationMultiples();
        $multiples = array_is_list($payload) ? $payload : (array) ($payload['valuation_multiples'] ?? []);

        return array_values(array_map(
            fn (array $multiple): array => $this->withTransportMeta($result, $multiple),
            array_filter($multiples, 'is_array'),
        ));
    }

    public function industryWaccRates(): array
    {
        if (! $this->live->isLive('mbie')) {
            throw IntegrationDisabledException::forService('mbie');
        }

        $result = $this->http->get(
            service: 'mbie',
            endpoint: $this->endpoint('industry-wacc'),
            query: $this->query(),
            cacheKey: 'integration:mbie:industry-wacc',
            fallback: fn (): array => $this->fake->fallbackIndustryWaccRates(),
        );

        $payload = is_array($result->data) ? $result->data : $this->fake->fallbackIndustryWaccRates();
        $rates = array_is_list($payload) ? $payload : (array) ($payload['industry_wacc_rates'] ?? []);

        return array_values(array_map(
            fn (array $rate): array => $this->withTransportMeta($result, $rate),
            array_filter($rates, 'is_array'),
        ));
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function withTransportMeta(IntegrationResult $result, array $record): array
    {
        return [
            ...$record,
            'source' => (string) ($record['source'] ?? 'mbie'),
            'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
            'degraded' => $result->fromFallback || (bool) ($record['degraded'] ?? false),
            'correlation_id' => $result->correlationId,
        ];
    }

    private function endpoint(string $resource = 'wage-rates'): string
    {
        $apiKey = $this->apiKey();

        return $apiKey === ''
            ? "fsa-disabled://mbie/missing-api-key/{$resource}"
            : rtrim((string) Config::get('integrations.mbie.base_url'), '/')."/{$resource}";
    }

    /**
     * @return array<string, mixed>
     */
    private function query(): array
    {
        $apiKey = $this->apiKey();

        return $apiKey === '' ? [] : ['api_key' => $apiKey];
    }

    private function apiKey(): string
    {
        return (string) ($this->credentials->get('mbie', 'api_key') ?? '');
    }
}
