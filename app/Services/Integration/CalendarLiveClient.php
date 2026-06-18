<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\CalendarConnection;
use App\Models\CalendarEventMapping;
use App\Models\Meeting;
use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Exceptions\IntegrationRequestFailedException;
use App\Services\Integration\Resilience\IntegrationResult;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use JsonException;

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
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'form_params' => array_filter([
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'client_id' => $this->credential('client_id'),
                    'client_secret' => $this->credential('client_secret'),
                    'scope' => $this->tokenExchangeScope(),
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
            ],
            fallback: null,
        );

        $this->requireLiveResult($result, 'calendar token exchange');

        $token = $this->withTransportMeta($result, []);
        if (! is_scalar($token['access_token'] ?? null) || trim((string) $token['access_token']) === '') {
            throw IntegrationRequestFailedException::forService(
                $this->provider(),
                'calendar token exchange',
                $result->correlationId,
                $this->providerFailureMessage($result),
            );
        }

        return $token;
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
        $result = $this->http->request(
            method: $method,
            service: $this->provider(),
            endpoint: $this->endpoint($this->eventPath($mapping)),
            options: [
                'headers' => [
                    'Authorization' => 'Bearer '.(string) ($token['access_token'] ?? ''),
                    'Accept' => 'application/json',
                ],
                'json' => $this->eventPayload($meeting),
            ],
            fallback: null,
        );

        $this->requireLiveResult($result, 'calendar event push');

        return $this->normalisePushedEvent($this->withTransportMeta($result, []), $meeting, $result->correlationId);
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
            endpoint: $this->endpoint($this->eventCollectionPath()),
            options: [
                'headers' => [
                    'Authorization' => 'Bearer '.(string) ($token['access_token'] ?? ''),
                    'Accept' => 'application/json',
                ],
                'query' => $this->pullQuery($connection),
            ],
            cacheKey: null,
            fallback: null,
        );

        $this->requireLiveResult($result, 'calendar event pull');

        return $this->normalisePulledEvents($this->withTransportMeta($result, []), $connection);
    }

    /**
     * @param  array<string, mixed>  $token
     */
    public function revoke(CalendarConnection $connection, array $token): void
    {
        $this->ensureLive();

        $endpoint = $this->endpoint('revoke');
        if ($endpoint === '') {
            return;
        }

        $this->http->request(
            method: 'POST',
            service: $this->provider(),
            endpoint: $endpoint,
            options: [
                'json' => [
                    'token' => (string) ($token['refresh_token'] ?? $token['access_token'] ?? ''),
                    'external_account_id' => $connection->external_account_id,
                ],
            ],
            fallback: null,
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

        if ($path === 'revoke') {
            return filled(Config::get($this->configKey('revoke_url')))
                ? $this->configuredUrl('revoke_url')
                : '';
        }

        return rtrim($this->configuredUrl('base_url'), '/').'/'.$path;
    }

    private function eventCollectionPath(): string
    {
        return match ($this->provider()) {
            CalendarConnection::PROVIDER_GOOGLE => 'calendars/primary/events',
            default => 'events',
        };
    }

    private function eventPath(?CalendarEventMapping $mapping): string
    {
        if (! $mapping instanceof CalendarEventMapping) {
            return $this->eventCollectionPath();
        }

        return $this->eventCollectionPath().'/'.rawurlencode($mapping->external_event_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(Meeting $meeting): array
    {
        $startsAt = $meeting->scheduled_at ?? now();
        $endsAt = $startsAt->copy()->addHour();
        $timezone = (string) Config::get('app.timezone', 'UTC');

        if ($this->provider() === CalendarConnection::PROVIDER_MICROSOFT) {
            return array_filter([
                'subject' => $meeting->title,
                'start' => [
                    'dateTime' => $startsAt->toIso8601String(),
                    'timeZone' => $timezone,
                ],
                'end' => [
                    'dateTime' => $endsAt->toIso8601String(),
                    'timeZone' => $timezone,
                ],
                'location' => filled($meeting->location) ? ['displayName' => $meeting->location] : null,
                'attendees' => $this->microsoftAttendees($meeting->attendees ?? []),
            ], fn (mixed $value): bool => $value !== null && $value !== []);
        }

        return array_filter([
            'summary' => $meeting->title,
            'start' => ['dateTime' => $startsAt->toIso8601String(), 'timeZone' => $timezone],
            'end' => ['dateTime' => $endsAt->toIso8601String(), 'timeZone' => $timezone],
            'location' => $meeting->location,
            'attendees' => $this->googleAttendees($meeting->attendees ?? []),
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function pullQuery(CalendarConnection $connection): array
    {
        if ($this->provider() === CalendarConnection::PROVIDER_MICROSOFT) {
            return [
                '$top' => 25,
                '$select' => 'id,subject,start,end,location,attendees,lastModifiedDateTime,changeKey',
            ];
        }

        return array_filter([
            'syncToken' => $connection->sync_token,
            'singleEvents' => true,
            'orderBy' => $connection->sync_token === null ? 'startTime' : null,
            'timeMin' => $connection->sync_token === null ? now()->subDay()->toIso8601String() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normalisePushedEvent(array $record, Meeting $meeting, string $correlationId): array
    {
        $event = $this->normaliseEvent($record);

        if (! is_scalar($event['external_event_id'] ?? null) || trim((string) $event['external_event_id']) === '') {
            throw IntegrationRequestFailedException::forService($this->provider(), 'calendar event push', $correlationId);
        }

        return [
            'title' => $meeting->title,
            'starts_at' => $meeting->scheduled_at,
            'ends_at' => $meeting->scheduled_at?->copy()->addHour(),
            'location' => $meeting->location,
            'attendees' => $meeting->attendees ?? [],
            ...$event,
            'source_badge' => $record['source_badge'] ?? 'live',
            'degraded' => (bool) ($record['degraded'] ?? false),
            'correlation_id' => $record['correlation_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normalisePulledEvents(array $record, CalendarConnection $connection): array
    {
        $items = $record['value'] ?? $record['items'] ?? $record['events'] ?? [];
        $items = is_array($items) ? $items : [];

        $events = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $event = $this->normaliseEvent($item);
            if (is_scalar($event['external_event_id'] ?? null) && trim((string) $event['external_event_id']) !== '') {
                $events[] = $event;
            }
        }

        return [
            'events' => $events,
            'sync_token' => $record['nextSyncToken'] ?? $record['sync_token'] ?? $connection->sync_token,
            'delta_link' => $record['@odata.deltaLink'] ?? $record['delta_link'] ?? $connection->delta_link,
            'source_badge' => $record['source_badge'] ?? 'live',
            'degraded' => (bool) ($record['degraded'] ?? false),
            'correlation_id' => $record['correlation_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normaliseEvent(array $record): array
    {
        return [
            'external_event_id' => $this->scalar($record['id'] ?? $record['external_event_id'] ?? null),
            'etag' => $this->scalar($record['changeKey'] ?? $record['etag'] ?? null),
            'updated_at' => $this->scalar($record['lastModifiedDateTime'] ?? $record['updated'] ?? $record['updated_at'] ?? null) ?? now()->toIso8601String(),
            'title' => $this->scalar($record['subject'] ?? $record['summary'] ?? $record['title'] ?? null),
            'starts_at' => $this->scalar(data_get($record, 'start.dateTime') ?? data_get($record, 'start.date') ?? $record['starts_at'] ?? null),
            'ends_at' => $this->scalar(data_get($record, 'end.dateTime') ?? data_get($record, 'end.date') ?? $record['ends_at'] ?? null),
            'location' => $this->scalar(data_get($record, 'location.displayName') ?? $record['location'] ?? null),
            'attendees' => $this->attendeeEmails($record['attendees'] ?? []),
        ];
    }

    /**
     * @param  array<int, mixed>  $attendees
     * @return array<int, array<string, mixed>>
     */
    private function microsoftAttendees(array $attendees): array
    {
        return collect($this->attendeeEmails($attendees))
            ->map(fn (string $email): array => [
                'emailAddress' => [
                    'address' => $email,
                    'name' => $email,
                ],
                'type' => 'required',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $attendees
     * @return array<int, array<string, string>>
     */
    private function googleAttendees(array $attendees): array
    {
        return collect($this->attendeeEmails($attendees))
            ->map(fn (string $email): array => ['email' => $email])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function attendeeEmails(mixed $attendees): array
    {
        if (! is_array($attendees)) {
            return [];
        }

        return collect($attendees)
            ->map(function (mixed $attendee): ?string {
                if (is_string($attendee)) {
                    return filter_var($attendee, FILTER_VALIDATE_EMAIL) ? $attendee : null;
                }

                if (is_array($attendee)) {
                    $email = data_get($attendee, 'emailAddress.address') ?? data_get($attendee, 'email') ?? data_get($attendee, 'address');

                    return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function scalar(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== ''
            ? (string) $value
            : null;
    }

    private function requireLiveResult(IntegrationResult $result, string $operation): void
    {
        if ($result->fromFallback || ! $result->successful()) {
            throw IntegrationRequestFailedException::forService(
                $this->provider(),
                $operation,
                $result->correlationId,
                $this->providerFailureMessage($result),
            );
        }
    }

    private function providerFailureMessage(IntegrationResult $result): ?string
    {
        if (! is_array($result->data)) {
            return null;
        }

        $errorPayload = $result->data['error_payload'] ?? $result->data;
        if (! is_array($errorPayload)) {
            return null;
        }

        $body = $errorPayload['body'] ?? null;
        if (is_string($body) && trim($body) !== '') {
            $decoded = $this->decodeProviderErrorBody($body);
            if ($decoded !== null) {
                return $decoded;
            }

            return $this->compactProviderMessage(strip_tags($body));
        }

        foreach (['message', 'reason', 'exception'] as $key) {
            $value = $errorPayload[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return $this->compactProviderMessage((string) $value);
            }
        }

        return null;
    }

    private function decodeProviderErrorBody(string $body): ?string
    {
        try {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        foreach (['error_description', 'message', 'error'] as $key) {
            $value = $decoded[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return $this->compactProviderMessage((string) $value);
            }
        }

        return null;
    }

    private function compactProviderMessage(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', trim($message)) ?? trim($message);
        $message = preg_replace('/(?:Trace ID|Correlation ID|Timestamp):\s*[^.]+\.?/i', '', $message) ?? $message;

        return Str::limit(trim($message), 240);
    }

    private function configKey(string $key): string
    {
        return "integrations.calendar.{$this->provider()}.{$key}";
    }

    private function tokenExchangeScope(): ?string
    {
        if ($this->provider() !== CalendarConnection::PROVIDER_MICROSOFT) {
            return null;
        }

        $configured = Config::get($this->configKey('scopes'));
        if (is_array($configured)) {
            $scopes = collect($configured)
                ->map(fn (mixed $scope): string => trim((string) $scope))
                ->filter()
                ->values()
                ->all();
        } elseif (is_string($configured) && trim($configured) !== '') {
            $scopes = collect(preg_split('/[\s,]+/', $configured) ?: [])
                ->map(fn (string $scope): string => trim($scope))
                ->filter()
                ->values()
                ->all();
        } else {
            $scopes = ['Calendars.ReadWrite', 'offline_access'];
        }

        return $scopes === [] ? null : implode(' ', $scopes);
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
