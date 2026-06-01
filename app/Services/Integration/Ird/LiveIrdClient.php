<?php

declare(strict_types=1);

namespace App\Services\Integration\Ird;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Ird\Contracts\IrdClient;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

final class LiveIrdClient implements IrdClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeIrdClient $fake,
        private readonly IntegrationActivationResolver $live,
        private readonly IntegrationCredentials $credentials,
    ) {}

    public function gstStatus(string $nzbn): array
    {
        if (! $this->live->isLive('ird')) {
            throw IntegrationDisabledException::forService('ird');
        }

        $apiKey = (string) ($this->credentials->get('ird', 'api_key') ?? '');
        $endpoint = $apiKey === ''
            ? 'fsa-disabled://ird/missing-api-key'
            : rtrim((string) Config::get('integrations.ird.base_url'), '/').'/gst-status/'.$nzbn;

        $result = $this->http->get(
            service: 'ird',
            endpoint: $endpoint,
            query: $apiKey === '' ? [] : ['api_key' => $apiKey],
            cacheKey: "integration:ird:gst:{$nzbn}",
            fallback: fn (): array => $this->fake->fallbackGstStatus($nzbn),
        );

        return is_array($result->data)
            ? [
                ...$result->data,
                'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
                'degraded' => $result->fromFallback,
                'correlation_id' => $result->correlationId,
            ]
            : $this->fake->fallbackGstStatus($nzbn);
    }

    public function legislativeChanges(): array
    {
        if (! $this->live->isLive('ird')) {
            throw IntegrationDisabledException::forService('ird');
        }

        return $this->fake->legislativeChanges();
    }
}
