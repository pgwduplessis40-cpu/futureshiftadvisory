<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\CalendarConnection;
use App\Models\CalendarEventMapping;
use App\Models\Meeting;
use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Resilience\IntegrationResult;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

abstract class CalendarLiveClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly object $fake,
        private ?IntegrationActivationResolver $live = null,
        private ?IntegrationCredentials $credentials = null,
    ) {}

    /**
     * @param  array<int, string>  $scopes
     */
    public function authorizeUrl(string $state, string $redirectUri, array $scopes): string
    {
        $query = http_build_query([
            'client_id' => $this->credential('client_id') ?: 'fixture-client',
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'response_mode' => 'query',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return rtrim($this->configuredUrl('authorize_url'), '?').'?'.$query;
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $this->ensureLive();

        $result = $this->http->request(
            method: 'POST',
            service: $this->provider(),
            endpoint: $this->endpoint('token'),
            options: [
                'json' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'client_id' => $this->credential('client_id'),
                    'client_secret' => $this->credential('client_secret'),
                ],
            ],
            fallback: fn (): array => $this->fake->fallbackToken(),
        );

        return $this->withTransportMeta($result, $this->fake->fallbackToken());
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public function pushEvent(
        CalendarConnection $connection,
        Meeting $meeting,
        array $token,
        ?CalendarEventMapping $mapping = null,
    ): array {
        $this->ensureLive();

        $method = $mapping instanceof CalendarEventMapping ? 'PATCH' : 'POST';
        $path = $mapping instanceof CalendarEventMapping
            ? 'events/'.rawurlencode($mapping->external_event_id)
            : 'events';

        $startsAt = $meeting->scheduled_at ?? now();
        $result = $this->http->request(
            method: $method,
            service: $this->provider(),
            endpoint: $this->endpoint($path),
            options: [
                'headers' => [
                    'Authorization' => 'Bearer '.(string) ($token['access_token'] ?? ''),
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'summary' => $meeting->title,
                    'start' => ['dateTime' => $startsAt->toIso8601String()],
                    'end' => ['dateTime' => $startsAt->copy()->addHour()->toIso8601String()],
                    'location' => $meeting->location,
                    'attendees' => $meeting->attendees ?? [],
                ],
            ],
            fallback: fn (): array => $this->fake->fallbackPushedEvent($meeting),
        );

        return $this->withTransportMeta($result, $this->fake->fallbackPushedEvent($meeting));
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public function pullEvents(CalendarConnection $connection, array $token): array
    {
        $this->ensureLive();

        $result = $this->http->request(
            method: 'GET',
            service: $this->provider(),
            endpoint: $this->endpoint('events'),
            options: [
                'headers' => [
                    'Authorization' => 'Bearer '.(string) ($token['access_token'] ?? ''),
                    'Accept' => 'application/json',
                ],
                'query' => array_filter([
                    'syncToken' => $connection->sync_token,
                    'deltaLink' => $connection->delta_link,
                ]),
            ],
            cacheKey: null,
            fallback: fn (): array => $this->fake->fallbackPulledEvents($connection->sync_token),
        );

        return $this->withTransportMeta($result, $this->fake->fallbackPulledEvents($connection->sync_token));
    }

    /**
     * @param  array<string, mixed>  $token
     */
    public function revoke(CalendarConnection $connection, array $token): void
    {
        $this->ensureLive();

        $this->http->request(
            method: 'POST',
            service: $this->provider(),
            endpoint: $this->endpoint('revoke'),
            options: [
                'json' => [
                    'token' => (string) ($token['refresh_token'] ?? $token['access_token'] ?? ''),
                    'external_account_id' => $connection->external_account_id,
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
            'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
            'degraded' => $result->fromFallback || (bool) ($record['degraded'] ?? false),
            'correlation_id' => $result->correlationId,
        ];
    }

    private function ensureLive(): void
    {
        if (! $this->live()->isLive($this->integrationKey())) {
            throw IntegrationDisabledException::forService($this->provider());
        }
    }

    private function endpoint(string $path): string
    {
        $clientSecret = $this->credential('client_secret');

        if ($clientSecret === '') {
            return "fsa-disabled://{$this->provider()}/missing-client-secret/{$path}";
        }

        if ($path === 'token') {
            $tokenUrl = $this->configuredUrl('token_url');

            return $tokenUrl === ''
                ? rtrim($this->configuredUrl('base_url'), '/').'/'.$path
                : $tokenUrl;
        }

        if ($path === 'revoke' && filled(Config::get($this->configKey('revoke_url')))) {
            return $this->configuredUrl('revoke_url');
        }

        return rtrim($this->configuredUrl('base_url'), '/').'/'.$path;
    }

    private function configKey(string $key): string
    {
        return "integrations.calendar.{$this->provider()}.{$key}";
    }

    private function configuredUrl(string $key): string
    {
        $url = (string) Config::get($this->configKey($key), '');
        $tenant = rawurlencode((string) Config::get($this->configKey('tenant'), 'common'));

        return str_replace('{tenant}', $tenant === '' ? 'common' : $tenant, $url);
    }

    private function integrationKey(): string
    {
        return match ($this->provider()) {
            'google' => 'google_calendar',
            'microsoft' => 'microsoft_calendar',
            default => $this->provider(),
        };
    }

    private function credential(string $field): string
    {
        return (string) ($this->credentials()->get($this->integrationKey(), $field) ?? '');
    }

    private function live(): IntegrationActivationResolver
    {
        return $this->live ??= app(IntegrationActivationResolver::class);
    }

    private function credentials(): IntegrationCredentials
    {
        return $this->credentials ??= app(IntegrationCredentials::class);
    }
}
