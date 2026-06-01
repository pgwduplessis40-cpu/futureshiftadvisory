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

        $apiKey = (string) ($this->credentials->get('companies_office', 'api_key') ?? '');
        $endpoint = $apiKey === ''
            ? 'fsa-disabled://companies-office/missing-api-key'
            : rtrim((string) Config::get('integrations.companies_office.base_url'), '/').'/companies/'.$nzbn;

        $result = $this->http->get(
            service: 'companies-office',
            endpoint: $endpoint,
            query: $apiKey === '' ? [] : ['api_key' => $apiKey],
            cacheKey: "integration:companies-office:{$nzbn}",
            fallback: fn (): array => $this->fake->fallbackCompanyProfile($nzbn),
        );

        return is_array($result->data)
            ? [
                ...$result->data,
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

        $apiKey = (string) ($this->credentials->get('incorporated_societies', 'api_key') ?? '');
        $endpoint = $apiKey === ''
            ? rtrim((string) Config::get('integrations.incorporated_societies.scrape_url'), '/').'/'.urlencode($identifier)
            : rtrim((string) Config::get('integrations.incorporated_societies.base_url'), '/').'/societies/'.urlencode($identifier);

        $result = $this->http->get(
            service: 'incorporated-societies',
            endpoint: $endpoint,
            query: $apiKey === '' ? [] : ['api_key' => $apiKey],
            cacheKey: 'integration:incorporated-societies:'.strtoupper(trim($identifier)),
            fallback: fn (): array => $this->fake->fallbackIncorporatedSocietyProfile($identifier),
        );

        return is_array($result->data)
            ? [
                ...$result->data,
                'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
                'degraded' => $result->fromFallback,
                'correlation_id' => $result->correlationId,
            ]
            : $this->fake->fallbackIncorporatedSocietyProfile($identifier);
    }
}
