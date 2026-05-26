<?php

declare(strict_types=1);

namespace App\Services\Integration\CharitiesServices;

use App\Services\Integration\CharitiesServices\Contracts\CharitiesServicesClient;
use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

final class LiveCharitiesServicesClient implements CharitiesServicesClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeCharitiesServicesClient $fake,
    ) {}

    public function charityProfile(string $registrationNumber): array
    {
        if (! (bool) Config::get('integrations.charities_services.live', false)) {
            throw IntegrationDisabledException::forService('charities-services');
        }

        $apiKey = (string) Config::get('integrations.charities_services.api_key', '');
        $endpoint = $apiKey === ''
            ? rtrim((string) Config::get('integrations.charities_services.scrape_url'), '/').'/'.urlencode($registrationNumber)
            : rtrim((string) Config::get('integrations.charities_services.base_url'), '/').'/charities/'.urlencode($registrationNumber);

        $result = $this->http->get(
            service: 'charities-services',
            endpoint: $endpoint,
            query: $apiKey === '' ? [] : ['api_key' => $apiKey],
            cacheKey: 'integration:charities-services:'.strtoupper(trim($registrationNumber)),
            fallback: fn (): array => $this->fake->fallbackCharityProfile($registrationNumber),
        );

        return is_array($result->data)
            ? [
                ...$result->data,
                'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
                'degraded' => $result->fromFallback,
                'correlation_id' => $result->correlationId,
            ]
            : $this->fake->fallbackCharityProfile($registrationNumber);
    }
}
