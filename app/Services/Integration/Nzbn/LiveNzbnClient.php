<?php

declare(strict_types=1);

namespace App\Services\Integration\Nzbn;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Nzbn\Contracts\NzbnClient;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

final class LiveNzbnClient implements NzbnClient
{
    private const SUBSCRIPTION_KEY_HEADER = 'Ocp-Apim-Subscription-Key';

    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeNzbnClient $fake,
        private readonly IntegrationActivationResolver $live,
        private readonly IntegrationCredentials $credentials,
    ) {}

    public function lookupByNzbn(string $nzbn): array
    {
        if (! $this->live->isLive('nzbn')) {
            throw IntegrationDisabledException::forService('nzbn');
        }

        $apiKey = (string) ($this->credentials->get('nzbn', 'api_key') ?? '');
        $endpoint = $apiKey === ''
            ? 'fsa-disabled://nzbn/missing-api-key'
            : rtrim((string) Config::get('integrations.nzbn.base_url'), '/').'/entities/'.$nzbn;

        $result = $this->http->get(
            service: 'nzbn',
            endpoint: $endpoint,
            cacheKey: "integration:nzbn:{$nzbn}",
            fallback: fn (): array => $this->fake->fallbackLookupByNzbn($nzbn),
            headers: $this->subscriptionHeaders($apiKey),
        );

        return is_array($result->data)
            ? [
                ...$this->normalise($result->data, $nzbn),
                'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
                'degraded' => $result->fromFallback,
                'correlation_id' => $result->correlationId,
            ]
            : $this->fake->fallbackLookupByNzbn($nzbn);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normalise(array $record, string $nzbn): array
    {
        return [
            ...$record,
            'nzbn' => (string) ($record['nzbn'] ?? $nzbn),
            'entity_name' => $record['entity_name']
                ?? data_get($record, 'entityName')
                ?? data_get($record, 'name')
                ?? data_get($record, 'entity.name'),
            'entity_type' => $record['entity_type']
                ?? data_get($record, 'entityTypeDescription')
                ?? data_get($record, 'entityType')
                ?? data_get($record, 'entity.type'),
            'status' => $record['status']
                ?? data_get($record, 'entityStatusDescription')
                ?? data_get($record, 'entityStatus')
                ?? data_get($record, 'statusDescription'),
            'registered_address' => $record['registered_address']
                ?? data_get($record, 'registeredAddress')
                ?? data_get($record, 'addresses.0'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function subscriptionHeaders(string $apiKey): array
    {
        return $apiKey === ''
            ? []
            : [
                self::SUBSCRIPTION_KEY_HEADER => $apiKey,
                'Accept' => 'application/json',
            ];
    }
}
