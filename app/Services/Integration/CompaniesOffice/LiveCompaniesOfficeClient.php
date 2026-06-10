<?php

declare(strict_types=1);

namespace App\Services\Integration\CompaniesOffice;

use App\Services\Integration\CompaniesOffice\Contracts\CompaniesOfficeClient;
use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

final class LiveCompaniesOfficeClient implements CompaniesOfficeClient
{
    private const SUBSCRIPTION_KEY_HEADER = 'Ocp-Apim-Subscription-Key';

    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeCompaniesOfficeClient $fake,
        private readonly IntegrationActivationResolver $live,
        private readonly IntegrationCredentials $credentials,
    ) {}

    public function companyProfile(string $nzbn): array
    {
        if (! $this->live->isLive('companies_office')) {
            throw IntegrationDisabledException::forService('companies-office');
        }

        $apiKey = $this->nzbnSubscriptionKey();
        $endpoint = $apiKey === ''
            ? 'fsa-disabled://companies-office/missing-api-key'
            : rtrim((string) Config::get('integrations.companies_office.base_url'), '/').'/entities/'.$nzbn;

        $result = $this->http->get(
            service: 'companies-office',
            endpoint: $endpoint,
            cacheKey: "integration:companies-office:{$nzbn}",
            fallback: fn (): array => $this->fake->fallbackCompanyProfile($nzbn),
            headers: $this->subscriptionHeaders($apiKey),
        );

        return is_array($result->data)
            ? [
                ...$this->normaliseCompanyProfile($result->data, $nzbn),
                'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
                'degraded' => $result->fromFallback,
                'correlation_id' => $result->correlationId,
            ]
            : $this->fake->fallbackCompanyProfile($nzbn);
    }

    public function directorsForCompany(string $nzbn): array
    {
        $profile = $this->companyProfile($nzbn);
        $directors = $profile['directors'] ?? [];

        return is_array($directors) ? array_values($directors) : [];
    }

    public function incorporatedSocietyProfile(string $identifier): array
    {
        if (! $this->live->isLive('incorporated_societies')) {
            throw IntegrationDisabledException::forService('incorporated-societies');
        }

        $apiKey = $this->nzbnSubscriptionKey();
        $query = [];
        $endpoint = $apiKey === ''
            ? 'fsa-disabled://incorporated-societies/missing-api-key'
            : rtrim((string) Config::get('integrations.incorporated_societies.base_url'), '/');

        if ($apiKey !== '' && preg_match('/^\d{13}$/', trim($identifier)) === 1) {
            $endpoint .= '/entities/'.trim($identifier);
        } elseif ($apiKey !== '') {
            $endpoint .= '/entities';
            $query = ['search' => trim($identifier), 'page-size' => 1];
        }

        $result = $this->http->get(
            service: 'incorporated-societies',
            endpoint: $endpoint,
            query: $query,
            cacheKey: 'integration:incorporated-societies:'.strtoupper(trim($identifier)),
            fallback: fn (): array => $this->fake->fallbackIncorporatedSocietyProfile($identifier),
            headers: $this->subscriptionHeaders($apiKey),
        );

        return is_array($result->data)
            ? [
                ...$this->normaliseSocietyProfile($result->data, $identifier),
                'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
                'degraded' => $result->fromFallback,
                'correlation_id' => $result->correlationId,
            ]
            : $this->fake->fallbackIncorporatedSocietyProfile($identifier);
    }

    private function nzbnSubscriptionKey(): string
    {
        return (string) (
            $this->credentials->get('nzbn', 'api_key')
            ?? $this->credentials->get('companies_office', 'api_key')
            ?? ''
        );
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normaliseCompanyProfile(array $record, string $nzbn): array
    {
        return [
            ...$record,
            'nzbn' => (string) ($record['nzbn'] ?? $nzbn),
            'company_number' => $record['company_number']
                ?? data_get($record, 'entityNumber')
                ?? data_get($record, 'companyNumber'),
            'company_name' => $record['company_name']
                ?? data_get($record, 'entityName')
                ?? data_get($record, 'companyName'),
            'status' => $record['status']
                ?? data_get($record, 'entityStatusDescription')
                ?? data_get($record, 'entityStatus'),
            'incorporation_date' => $record['incorporation_date']
                ?? data_get($record, 'registrationDate')
                ?? data_get($record, 'incorporationDate'),
            'directors' => $record['directors']
                ?? data_get($record, 'roles.directors')
                ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normaliseSocietyProfile(array $record, string $identifier): array
    {
        $candidate = data_get($record, 'items.0');
        if (is_array($candidate)) {
            $record = $candidate;
        }

        return [
            ...$record,
            'society_number' => $record['society_number']
                ?? data_get($record, 'entityNumber')
                ?? $identifier,
            'name' => $record['name']
                ?? data_get($record, 'entityName')
                ?? data_get($record, 'companyName'),
            'status' => $record['status']
                ?? data_get($record, 'entityStatusDescription')
                ?? data_get($record, 'entityStatus'),
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
