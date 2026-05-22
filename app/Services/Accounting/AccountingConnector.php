<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;

final class AccountingConnector
{
    public function __construct(
        private readonly AccountingClientResolver $clients,
        private readonly KeyEnvelope $envelope,
        private readonly AuditWriter $audit,
    ) {}

    public function authorizeUrl(Client $client, User $user, string $provider): string
    {
        $this->assertProvider($provider);
        $query = http_build_query([
            'client_id' => (string) Config::get("integrations.accounting.{$provider}.client_id", 'fixture-client'),
            'redirect_uri' => $this->callbackUrl($client, $provider),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes($provider)),
            'state' => $this->stateFor($client, $provider, $user),
        ]);

        return rtrim((string) Config::get("integrations.accounting.{$provider}.authorize_url"), '?').'?'.$query;
    }

    public function connectFromCallback(Client $client, User $user, string $provider, string $code, string $state): AccountingConnection
    {
        $this->assertProvider($provider);
        if (! $this->stateIsValid($state, $client, $provider, $user)) {
            throw new InvalidArgumentException('Invalid accounting connection state.');
        }

        $token = $this->clients
            ->client($provider)
            ->exchangeCodeForToken($code, $this->callbackUrl($client, $provider));

        $tokenJson = $this->json($token);
        $tokenEnvelope = $this->envelope->encrypt($tokenJson);

        return DB::transaction(function () use ($client, $user, $provider, $token, $tokenEnvelope): AccountingConnection {
            AccountingConnection::query()
                ->where('client_id', $client->getKey())
                ->where('provider', $provider)
                ->where('status', AccountingConnection::STATUS_CONNECTED)
                ->update([
                    'status' => AccountingConnection::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'revoked_by_user_id' => $user->getKey(),
                ]);

            $connection = AccountingConnection::query()->create([
                'client_id' => $client->getKey(),
                'provider' => $provider,
                'external_tenant_id' => $token['external_tenant_id'] ?? null,
                'status' => AccountingConnection::STATUS_CONNECTED,
                'token_envelope' => $tokenEnvelope,
                'token_envelope_meta' => $this->envelope->inspect($tokenEnvelope),
                'scopes' => $token['scopes'] ?? $this->scopes($provider),
                'connected_by_user_id' => $user->getKey(),
                'connected_at' => now(),
            ]);

            $this->audit->record('accounting_connection.connected', subject: $connection, actor: $user, after: [
                'client_id' => $client->id,
                'provider' => $provider,
                'source_badge' => $token['source_badge'] ?? null,
                'degraded' => $token['degraded'] ?? false,
            ]);

            return $connection;
        });
    }

    public function revoke(AccountingConnection $connection, User $user): AccountingConnection
    {
        $token = $this->decryptToken($connection);
        $this->clients->client($connection->provider)->revoke($connection, $token);

        $connection->forceFill([
            'status' => AccountingConnection::STATUS_REVOKED,
            'revoked_at' => now(),
            'revoked_by_user_id' => $user->getKey(),
        ])->save();

        $this->audit->record('accounting_connection.revoked', subject: $connection, actor: $user, after: [
            'client_id' => $connection->client_id,
            'provider' => $connection->provider,
        ]);

        return $connection->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function decryptToken(AccountingConnection $connection): array
    {
        try {
            $decoded = json_decode($this->envelope->decrypt($connection->token_envelope), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Stored accounting token is not valid JSON.', previous: $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<int, string>
     */
    public function scopes(string $provider): array
    {
        return match ($provider) {
            AccountingConnection::PROVIDER_XERO => ['accounting.reports.read', 'offline_access'],
            AccountingConnection::PROVIDER_MYOB => ['CompanyFile', 'offline_access'],
            AccountingConnection::PROVIDER_QUICKBOOKS => ['com.intuit.quickbooks.accounting', 'offline_access'],
            default => [],
        };
    }

    private function callbackUrl(Client $client, string $provider): string
    {
        return route('advisor.clients.accounting.callback', [$client, $provider]);
    }

    private function assertProvider(string $provider): void
    {
        if (! AccountingConnection::validProvider($provider)) {
            throw new InvalidArgumentException("Unsupported accounting provider [{$provider}].");
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
            throw new InvalidArgumentException('Unable to encode accounting payload.', previous: $e);
        }
    }
}
