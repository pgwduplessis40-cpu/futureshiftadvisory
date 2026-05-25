<?php

declare(strict_types=1);

namespace App\Services\Integration\BusinessTools;

use App\Models\NzToolConnection;
use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Resilience\IntegrationResult;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

abstract class NzBusinessToolLiveClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly object $fake,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $this->ensureLive();

        $result = $this->http->request(
            method: 'POST',
            service: $this->provider(),
            endpoint: $this->endpoint('oauth/token'),
            options: [
                'json' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'client_id' => (string) Config::get($this->configKey('client_id'), ''),
                    'client_secret' => (string) Config::get($this->configKey('client_secret'), ''),
                ],
            ],
            cacheKey: null,
            fallback: fn (): array => $this->fake->fallbackToken(),
        );

        return $this->withTransportMeta($result, $this->fake->fallbackToken());
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public function businessSnapshot(NzToolConnection $connection, array $token): array
    {
        $this->ensureLive();

        $result = $this->http->request(
            method: 'GET',
            service: $this->provider(),
            endpoint: $this->endpoint('business/snapshot'),
            options: [
                'headers' => [
                    'Authorization' => 'Bearer '.(string) ($token['access_token'] ?? ''),
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'tenant_id' => $connection->external_tenant_id,
                ],
            ],
            cacheKey: "integration:{$this->provider()}:business-snapshot:{$connection->id}",
            fallback: fn (): array => $this->fake->fallbackSnapshot(),
        );

        return $this->withTransportMeta($result, $this->fake->fallbackSnapshot());
    }

    /**
     * @param  array<string, mixed>  $token
     */
    public function revoke(NzToolConnection $connection, array $token): void
    {
        $this->ensureLive();

        $this->http->request(
            method: 'POST',
            service: $this->provider(),
            endpoint: $this->endpoint('oauth/revoke'),
            options: [
                'json' => [
                    'token' => (string) ($token['access_token'] ?? ''),
                    'tenant_id' => $connection->external_tenant_id,
                ],
            ],
            fallback: fn (): array => ['revoked' => true],
        );
    }

    abstract protected function provider(): string;

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function withTransportMeta(IntegrationResult $result, array $fallback): array
    {
        $record = is_array($result->data) ? $result->data : $fallback;

        return [
            ...$record,
            'source' => (string) ($record['source'] ?? $this->provider()),
            'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
            'degraded' => $result->fromFallback || (bool) ($record['degraded'] ?? false),
            'correlation_id' => $result->correlationId,
        ];
    }

    private function ensureLive(): void
    {
        if (! (bool) Config::get($this->configKey('live'), false)) {
            throw IntegrationDisabledException::forService($this->provider());
        }
    }

    private function endpoint(string $path): string
    {
        $clientSecret = (string) Config::get($this->configKey('client_secret'), '');

        return $clientSecret === ''
            ? "fsa-disabled://{$this->provider()}/missing-client-secret/{$path}"
            : rtrim((string) Config::get($this->configKey('base_url')), '/').'/'.$path;
    }

    private function configKey(string $key): string
    {
        return "integrations.business_tools.{$this->provider()}.{$key}";
    }
}
