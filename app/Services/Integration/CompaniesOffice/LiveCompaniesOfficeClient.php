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
}
