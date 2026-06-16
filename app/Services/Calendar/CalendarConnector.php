<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\CalendarConnection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

final class CalendarConnector
{
    public function __construct(
        private readonly CalendarClientResolver $clients,
        private readonly KeyEnvelope $envelope,
        private readonly AuditWriter $audit,
    ) {}

    public function authorizeUrl(User $user, string $provider): string
    {
        $this->assertProvider($provider);

        return $this->clients
            ->client($provider)
            ->authorizeUrl($this->stateFor($provider, $user), $this->callbackUrl($provider), $this->scopes($provider));
    }

    public function connectFromCallback(User $user, string $provider, string $code, string $state): CalendarConnection
    {
        $this->assertProvider($provider);
        if (! $this->stateIsValid($state, $provider, $user)) {
            throw new InvalidArgumentException('Invalid calendar connection state.');
        }

        $token = $this->clients
            ->client($provider)
            ->exchangeCodeForToken($code, $this->callbackUrl($provider));

        $accessToken = (string) ($token['access_token'] ?? '');
        if ($accessToken === '') {
            throw new InvalidArgumentException('Calendar provider did not return an access token.');
        }

        $refreshToken = (string) ($token['refresh_token'] ?? '');
        $accessEnvelope = $this->envelope->encrypt($accessToken);
        $refreshEnvelope = $refreshToken === '' ? null : $this->envelope->encrypt($refreshToken);
        $externalAccountId = (string) ($token['external_account_id'] ?? $token['external_account_email'] ?? $user->email);

        return DB::transaction(function () use ($accessEnvelope, $externalAccountId, $provider, $refreshEnvelope, $token, $user): CalendarConnection {
            $connection = CalendarConnection::query()->updateOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'provider' => $provider,
                    'external_account_id' => $externalAccountId,
                ],
                [
                    'external_account_email' => $token['external_account_email'] ?? null,
                    'access_token_envelope' => $accessEnvelope,
                    'access_token_envelope_meta' => $this->envelope->inspect($accessEnvelope),
                    'refresh_token_envelope' => $refreshEnvelope,
                    'refresh_token_envelope_meta' => $refreshEnvelope === null ? null : $this->envelope->inspect($refreshEnvelope),
                    'token_expires_at' => now()->addSeconds(max(60, (int) ($token['expires_in'] ?? 3600))),
                    'sync_token' => $token['sync_token'] ?? null,
                    'delta_link' => $token['delta_link'] ?? null,
                    'status' => CalendarConnection::STATUS_CONNECTED,
                    'last_synced_at' => null,
                ],
            );

            $this->audit->record('calendar_connection.connected', subject: $connection, actor: $user, after: [
                'provider' => $provider,
                'external_account_id' => $externalAccountId,
                'source_badge' => $token['source_badge'] ?? null,
                'degraded' => $token['degraded'] ?? false,
            ]);

            return $connection;
        });
    }

    public function revoke(CalendarConnection $connection, User $user): CalendarConnection
    {
        $this->clients
            ->client($connection->provider)
            ->revoke($connection, $this->decryptToken($connection));

        $connection->forceFill([
            'status' => CalendarConnection::STATUS_REVOKED,
        ])->save();

        $this->audit->record('calendar_connection.revoked', subject: $connection, actor: $user, after: [
            'provider' => $connection->provider,
            'external_account_id' => $connection->external_account_id,
        ]);

        return $connection->refresh();
    }

    /**
     * @return array<string, string|null>
     */
    public function decryptToken(CalendarConnection $connection): array
    {
        return [
            'access_token' => $this->envelope->decrypt($connection->access_token_envelope),
            'refresh_token' => $connection->refresh_token_envelope === null
                ? null
                : $this->envelope->decrypt($connection->refresh_token_envelope),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function scopes(string $provider): array
    {
        $configured = Config::get("integrations.calendar.{$provider}.scopes");
        if (is_array($configured)) {
            return collect($configured)
                ->map(fn (mixed $scope): string => trim((string) $scope))
                ->filter()
                ->values()
                ->all();
        }

        if (is_string($configured) && trim($configured) !== '') {
            return Str::of($configured)
                ->replace(',', ' ')
                ->explode(' ')
                ->map(fn (string $scope): string => trim($scope))
                ->filter()
                ->values()
                ->all();
        }

        return match ($provider) {
            CalendarConnection::PROVIDER_GOOGLE => ['https://www.googleapis.com/auth/calendar.events', 'offline_access'],
            CalendarConnection::PROVIDER_MICROSOFT => ['Calendars.ReadWrite', 'offline_access'],
            default => [],
        };
    }

    private function callbackUrl(string $provider): string
    {
        return route('calendar.callback', $provider);
    }

    private function assertProvider(string $provider): void
    {
        if (! CalendarConnection::validProvider($provider)) {
            throw new InvalidArgumentException("Unsupported calendar provider [{$provider}].");
        }
    }

    private function stateFor(string $provider, User $user): string
    {
        $payload = $this->json([
            'provider' => $provider,
            'user_id' => (string) $user->getKey(),
        ]);
        $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, (string) Config::get('app.key'));

        return $encoded.'.'.$signature;
    }

    private function stateIsValid(string $state, string $provider, User $user): bool
    {
        [$encoded, $signature] = array_pad(explode('.', $state, 2), 2, '');
        if ($encoded === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $encoded, (string) Config::get('app.key'));
        if (! hash_equals($expected, $signature)) {
            return false;
        }

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        if (! is_string($json)) {
            return false;
        }

        try {
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return is_array($payload)
            && (string) ($payload['provider'] ?? '') === $provider
            && (string) ($payload['user_id'] ?? '') === (string) $user->getKey();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function json(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Unable to encode calendar payload.', previous: $e);
        }
    }
}
