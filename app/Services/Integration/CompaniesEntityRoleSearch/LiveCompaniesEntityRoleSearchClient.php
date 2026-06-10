<?php

declare(strict_types=1);

namespace App\Services\Integration\CompaniesEntityRoleSearch;

use App\Services\Integration\CompaniesEntityRoleSearch\Contracts\CompaniesEntityRoleSearchClient;
use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

final class LiveCompaniesEntityRoleSearchClient implements CompaniesEntityRoleSearchClient
{
    private const SUBSCRIPTION_KEY_HEADER = 'Ocp-Apim-Subscription-Key';

    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeCompaniesEntityRoleSearchClient $fake,
        private readonly IntegrationActivationResolver $live,
        private readonly IntegrationCredentials $credentials,
    ) {}

    public function search(
        string $name,
        string $roleType = 'ALL',
        bool $registeredOnly = true,
        int $page = 1,
        int $pageSize = 25,
    ): array {
        if (! $this->live->isLive('companies_entity_role_search')) {
            throw IntegrationDisabledException::forService('companies-entity-role-search');
        }

        $name = trim($name);
        $roleType = strtoupper(trim($roleType));
        if (! in_array($roleType, ['SHR', 'DIR', 'ALL'], true)) {
            $roleType = 'ALL';
        }

        $apiKey = $this->subscriptionKey();
        $endpoint = $apiKey === ''
            ? 'fsa-disabled://companies-entity-role-search/missing-api-key'
            : $this->endpoint();

        $result = $this->http->get(
            service: 'companies-entity-role-search',
            endpoint: $endpoint,
            query: [
                'name' => $name,
                'role-type' => $roleType,
                'registered-only' => $registeredOnly ? 'true' : 'false',
                'page' => max(1, $page),
                'page-size' => min(100, max(1, $pageSize)),
            ],
            cacheKey: 'integration:companies-entity-role-search:'.sha1(json_encode([$name, $roleType, $registeredOnly, $page, $pageSize])),
            fallback: fn (): array => $this->fake->fallbackSearch($name),
            headers: $this->subscriptionHeaders($apiKey),
        );

        return is_array($result->data)
            ? [
                ...$result->data,
                'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
                'degraded' => $result->fromFallback,
                'correlation_id' => $result->correlationId,
            ]
            : $this->fake->fallbackSearch($name);
    }

    private function endpoint(): string
    {
        $base = rtrim((string) Config::get('integrations.companies_entity_role_search.base_url'), '/');
        $path = trim((string) Config::get('integrations.companies_entity_role_search.search_path', ''), '/');

        return $path === '' ? $base : "{$base}/{$path}";
    }

    private function subscriptionKey(): string
    {
        return (string) (
            $this->credentials->get('companies_entity_role_search', 'api_key')
            ?? $this->credentials->get('companies_office', 'api_key')
            ?? ''
        );
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
