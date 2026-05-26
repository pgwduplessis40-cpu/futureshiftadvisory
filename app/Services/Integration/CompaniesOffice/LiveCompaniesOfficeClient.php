<?php

declare(strict_types=1);

namespace App\Services\Integration\CompaniesOffice;

use App\Services\Integration\CompaniesOffice\Contracts\CompaniesOfficeClient;
use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

final class LiveCompaniesOfficeClient implements CompaniesOfficeClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeCompaniesOfficeClient $fake,
    ) {}

    public function companyProfile(string $nzbn): array
    {
        if (! (bool) Config::get('integrations.companies_office.live', false)) {
            throw IntegrationDisabledException::forService('companies-office');
        }

        $apiKey = (string) Config::get('integrations.companies_office.api_key', '');
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
        if (! (bool) Config::get('integrations.incorporated_societies.live', false)) {
            throw IntegrationDisabledException::forService('incorporated-societies');
        }

        $apiKey = (string) Config::get('integrations.incorporated_societies.api_key', '');
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
