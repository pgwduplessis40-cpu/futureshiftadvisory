<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\IntegrationCredentials;
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
        private readonly IntegrationCredentials $credentials,
    ) {}

    public function authorizeUrl(Client $client, User $user, string $provider): string
    {
        $this->assertProvider($provider);
        $query = http_build_query([
            'client_id' => (string) ($this->credentials->get($provider, 'client_id') ?? 'fixture-client'),
            'redirect_uri' => $this->callbackUrl($provider),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes($provider)),
            'state' => $this->stateFor($client, $provider, $user),
        ]);

        return rtrim((string) Config::get("integrations.accounting.{$provider}.authorize_url"), '?').'?'.$query;
    }

    public function connectFromCallback(Client $client, User $user, string $provider, string $code, string $state, ?string $redirectUri = null): AccountingConnection
    {
        $this->assertProvider($provider);
        if (! $this->stateIsValid($state, $client, $provider, $user)) {
            throw new InvalidArgumentException('Invalid accounting connection state.');
        }

        $token = $this->clients
            ->client($provider)
            ->exchangeCodeForToken($code, $redirectUri ?? $this->callbackUrl($provider));

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
            AccountingConnection::PROVIDER_XERO => [
                'accounting.reports.balancesheet.read',
                'accounting.reports.profitandloss.read',
                'accounting.reports.banksummary.read',
                'offline_access',
            ],
            AccountingConnection::PROVIDER_MYOB => ['CompanyFile', 'offline_access'],
            AccountingConnection::PROVIDER_QUICKBOOKS => ['com.intuit.quickbooks.accounting', 'offline_access'],
            AccountingConnection::PROVIDER_SAGE => ['accounting.read', 'offline_access'],
            AccountingConnection::PROVIDER_FIGURED => ['financials.read', 'offline_access'],
            AccountingConnection::PROVIDER_WORKFLOWMAX => ['workflowmax.read', 'offline_access'],
            default => [],
        };
    }

    public function clientFromState(string $state, string $provider, User $user): Client
    {
        $this->assertProvider($provider);
        $payload = $this->statePayload($state);

        if (
            (string) ($payload['provider'] ?? '') !== $provider
            || (string) ($payload['user_id'] ?? '') !== (string) $user->getKey()
            || (string) ($payload['client_id'] ?? '') === ''
        ) {
            throw new InvalidArgumentException('Invalid accounting connection state.');
        }

        /** @var Client|null $client */
        $client = Client::query()->find((string) $payload['client_id']);

        if (! $client instanceof Client) {
            throw new InvalidArgumentException('Accounting connection state client was not found.');
        }

        return $client;
    }

    public function callbackUrl(string $provider): string
    {
        return route('advisor.accounting.callback', $provider);
    }

    public function legacyCallbackUrl(Client $client, string $provider): string
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
        try {
            $payload = $this->statePayload($state);
        } catch (InvalidArgumentException) {
            return false;
        }

        return (string) ($payload['client_id'] ?? '') === (string) $client->getKey()
            && (string) ($payload['provider'] ?? '') === $provider
            && (string) ($payload['user_id'] ?? '') === (string) $user->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    private function statePayload(string $state): array
    {
        [$encoded, $signature] = array_pad(explode('.', $state, 2), 2, '');
        if ($encoded === '' || $signature === '') {
            throw new InvalidArgumentException('Invalid accounting connection state.');
        }

        $expected = hash_hmac('sha256', $encoded, (string) Config::get('app.key'));
        if (! hash_equals($expected, $signature)) {
            throw new InvalidArgumentException('Invalid accounting connection state.');
        }

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        if (! is_string($json)) {
            throw new InvalidArgumentException('Invalid accounting connection state.');
        }

        try {
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid accounting connection state.', previous: $e);
        }

        if (! is_array($payload)) {
            throw new InvalidArgumentException('Invalid accounting connection state.');
        }

        return $payload;
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
