<?php

declare(strict_types=1);

namespace App\Services\NzTools;

use App\Models\Client;
use App\Models\NzToolConnection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use JsonException;

final class NzToolConnector
{
    public function __construct(
        private readonly NzToolClientResolver $clients,
        private readonly KeyEnvelope $envelope,
        private readonly AuditWriter $audit,
    ) {}

    public function authorizeUrl(Client $client, User $user, string $provider): string
    {
        $this->assertProvider($provider);
        $query = http_build_query([
            'client_id' => (string) Config::get("integrations.business_tools.{$provider}.client_id", 'fixture-client'),
            'redirect_uri' => $this->callbackUrl($client, $provider),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes($provider)),
            'state' => $this->stateFor($client, $provider, $user),
        ]);

        return rtrim((string) Config::get("integrations.business_tools.{$provider}.authorize_url"), '?').'?'.$query;
    }

    public function connectFromCallback(Client $client, User $user, string $provider, string $code, string $state): NzToolConnection
    {
        $this->assertProvider($provider);
        if (! $this->stateIsValid($state, $client, $provider, $user)) {
            throw new InvalidArgumentException('Invalid NZ business tool connection state.');
        }

        $token = $this->clients
            ->client($provider)
            ->exchangeCodeForToken($code, $this->callbackUrl($client, $provider));

        $tokenEnvelope = $this->envelope->encrypt($this->json($token));

        return DB::transaction(function () use ($client, $user, $provider, $token, $tokenEnvelope): NzToolConnection {
            NzToolConnection::query()
                ->where('client_id', $client->getKey())
                ->where('provider', $provider)
                ->where('status', NzToolConnection::STATUS_CONNECTED)
                ->update([
                    'status' => NzToolConnection::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'revoked_by_user_id' => $user->getKey(),
                ]);

            $connection = NzToolConnection::query()->create([
                'client_id' => $client->getKey(),
                'provider' => $provider,
                'external_tenant_id' => $token['external_tenant_id'] ?? null,
                'status' => NzToolConnection::STATUS_CONNECTED,
                'token_envelope' => $tokenEnvelope,
                'token_envelope_meta' => $this->envelope->inspect($tokenEnvelope),
                'scopes' => $token['scopes'] ?? $this->scopes($provider),
                'connected_by_user_id' => $user->getKey(),
                'connected_at' => now(),
            ]);

            $this->audit->record('nz_tool_connection.connected', subject: $connection, actor: $user, after: [
                'client_id' => $client->id,
                'provider' => $provider,
                'source_badge' => $token['source_badge'] ?? null,
                'degraded' => $token['degraded'] ?? false,
            ]);

            return $connection;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function sync(NzToolConnection $connection, User $user): array
    {
        if (! $connection->connected()) {
            throw new InvalidArgumentException('NZ business tool connection is not active.');
        }

        $snapshot = $this->clients
            ->client($connection->provider)
            ->businessSnapshot($connection, $this->decryptToken($connection));

        $connection->forceFill([
            'last_sync_payload' => $snapshot,
            'last_synced_at' => now(),
        ])->save();

        $this->audit->record('nz_tool_connection.synced', subject: $connection, actor: $user, after: [
            'provider' => $connection->provider,
            'source_badge' => $snapshot['source_badge'] ?? null,
            'degraded' => $snapshot['degraded'] ?? false,
        ]);

        return $snapshot;
    }

    public function revoke(NzToolConnection $connection, User $user): NzToolConnection
    {
        $this->clients
            ->client($connection->provider)
            ->revoke($connection, $this->decryptToken($connection));

        $connection->forceFill([
            'status' => NzToolConnection::STATUS_REVOKED,
            'revoked_at' => now(),
            'revoked_by_user_id' => $user->getKey(),
        ])->save();

        $this->audit->record('nz_tool_connection.revoked', subject: $connection, actor: $user, after: [
            'provider' => $connection->provider,
            'client_id' => $connection->client_id,
        ]);

        return $connection->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function decryptToken(NzToolConnection $connection): array
    {
        try {
            $decoded = json_decode($this->envelope->decrypt($connection->token_envelope), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Stored NZ business tool token is not valid JSON.', previous: $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<int, string>
     */
    public function scopes(string $provider): array
    {
        return match ($provider) {
            NzToolConnection::PROVIDER_EMPLOYMENT_HERO => ['employees.read', 'leave.read', 'payroll.read'],
            NzToolConnection::PROVIDER_CIN7 => ['inventory.read', 'sales.read', 'purchasing.read'],
            NzToolConnection::PROVIDER_TRADIFY => ['jobs.read', 'invoices.read', 'timesheets.read'],
            default => [],
        };
    }

    private function callbackUrl(Client $client, string $provider): string
    {
        $configured = Config::get("integrations.business_tools.{$provider}.redirect_uri");
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return URL::to("/integrations/nz-tools/{$client->getKey()}/{$provider}/callback");
    }

    private function assertProvider(string $provider): void
    {
        if (! NzToolConnection::validProvider($provider)) {
            throw new InvalidArgumentException("Unsupported NZ business tool provider [{$provider}].");
        }
    }

    private function stateFor(Client $client, string $provider, User $user): string
    {
        $payload = $this->json([
            'client_id' => (string) $client->getKey(),
            'provider' => $provider,
            'user_id' => (string) $user->getKey(),
        ]);
        $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, (string) Config::get('app.key'));

        return $encoded.'.'.$signature;
    }

    private function stateIsValid(string $state, Client $client, string $provider, User $user): bool
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
            && (string) ($payload['client_id'] ?? '') === (string) $client->getKey()
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
            throw new InvalidArgumentException('Unable to encode NZ business tool payload.', previous: $e);
        }
    }
}
