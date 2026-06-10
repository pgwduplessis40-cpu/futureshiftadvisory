<?php

declare(strict_types=1);

namespace App\Services\Integration\Ppsr;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Ppsr\Contracts\PpsrClient;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

final class LivePpsrClient implements PpsrClient
{
    private const SUBSCRIPTION_KEY_HEADER = 'Ocp-Apim-Subscription-Key';

    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakePpsrClient $fake,
        private readonly IntegrationActivationResolver $live,
        private readonly IntegrationCredentials $credentials,
    ) {}

    public function securityInterests(string $nzbn): array
    {
        $nzbn = trim($nzbn);

        if (! $this->live->isLive('ppsr')) {
            throw IntegrationDisabledException::forService('ppsr');
        }

        $apiKey = (string) ($this->credentials->get('ppsr', 'api_key') ?? '');
        $endpoint = $apiKey === ''
            ? 'fsa-disabled://ppsr/missing-api-key'
            : $this->endpoint();

        $result = $this->http->post(
            service: 'ppsr',
            endpoint: $endpoint,
            payload: [
                'searchBy' => 'debtorOrganisation',
                'legitimateSearchReason' => 'yes',
                'searchByDebtorOrganisation' => [
                    'nzbn' => $nzbn,
                ],
            ],
            cacheKey: "integration:ppsr:security-interests:{$nzbn}",
            fallback: fn (): array => $this->fake->fallbackSecurityInterests($nzbn),
            headers: $this->headers($apiKey),
        );

        if (! is_array($result->data)) {
            return $this->fake->fallbackSecurityInterests($nzbn);
        }

        $items = $result->data['items'] ?? $result->data['financingStatements'] ?? $result->data;
        if (! is_array($items)) {
            return $this->fake->fallbackSecurityInterests($nzbn);
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                ...$item,
                'debtor_nzbn' => $item['debtor_nzbn'] ?? $nzbn,
                'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
                'degraded' => $result->fromFallback,
                'correlation_id' => $result->correlationId,
            ])
            ->values()
            ->all();
    }

    private function endpoint(): string
    {
        $base = rtrim((string) Config::get('integrations.ppsr.base_url'), '/');
        $path = trim((string) Config::get('integrations.ppsr.security_interests_path'), '/');

        return "{$base}/{$path}";
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $apiKey): array
    {
        $headers = $apiKey === ''
            ? []
            : [
                self::SUBSCRIPTION_KEY_HEADER => $apiKey,
                'Accept' => 'application/json',
            ];

        $oauthToken = (string) Config::get('integrations.ppsr.oauth_token', '');
        if ($oauthToken !== '') {
            $headers['Authorization'] = "Bearer {$oauthToken}";
        }

        return $headers;
    }
}
