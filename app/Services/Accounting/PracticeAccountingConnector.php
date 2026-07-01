<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingConnection;
use App\Models\PracticeAccountingConnection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Xero\LiveXeroClient;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;

final class PracticeAccountingConnector
{
    public function __construct(
        private readonly LiveXeroClient $xero,
        private readonly KeyEnvelope $envelope,
        private readonly AuditWriter $audit,
        private readonly IntegrationCredentials $credentials,
    ) {}

    public function authorizeUrl(User $user, string $provider): string
    {
        $this->assertProvider($provider);

        $query = http_build_query([
            'client_id' => (string) ($this->credentials->get($provider, 'client_id') ?? ''),
            'redirect_uri' => $this->callbackUrl($provider),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes($provider)),
            'state' => $this->stateFor($provider, $user),
        ]);

        return rtrim((string) Config::get("integrations.accounting.{$provider}.authorize_url"), '?').'?'.$query;
    }

    public function connectFromCallback(User $user, string $provider, string $code, string $state): PracticeAccountingConnection
    {
        $this->assertProvider($provider);
        if (! $this->stateIsValid($state, $provider, $user)) {
            throw new InvalidArgumentException('Invalid practice accounting connection state.');
        }

        $token = $this->xero->exchangeCodeForToken($code, $this->callbackUrl($provider));
        $tokenEnvelope = $this->envelope->encrypt($this->json($token));

        return DB::transaction(function () use ($provider, $token, $tokenEnvelope, $user): PracticeAccountingConnection {
            PracticeAccountingConnection::query()
                ->where('provider', $provider)
                ->where('status', PracticeAccountingConnection::STATUS_CONNECTED)
                ->update([
                    'status' => PracticeAccountingConnection::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'revoked_by_user_id' => $user->getKey(),
                ]);

            $connection = PracticeAccountingConnection::query()->create([
                'provider' => $provider,
                'external_tenant_id' => $token['external_tenant_id'] ?? null,
                'external_tenant_name' => $token['external_tenant_name'] ?? null,
                'external_tenant_type' => $token['external_tenant_type'] ?? null,
                'status' => PracticeAccountingConnection::STATUS_CONNECTED,
                'token_envelope' => $tokenEnvelope,
                'token_envelope_meta' => $this->envelope->inspect($tokenEnvelope),
                'scopes' => $token['scopes'] ?? $this->scopes($provider),
                'connected_by_user_id' => $user->getKey(),
                'connected_at' => now(),
            ]);

            $this->audit->record('practice_accounting_connection.connected', subject: $connection, actor: $user, after: [
                'provider' => $provider,
                'external_tenant_id' => $connection->external_tenant_id,
                'external_tenant_name' => $connection->external_tenant_name,
            ]);

            return $connection;
        });
    }

    public function active(string $provider = AccountingConnection::PROVIDER_XERO): ?PracticeAccountingConnection
    {
        $this->assertProvider($provider);

        return PracticeAccountingConnection::query()
            ->where('provider', $provider)
            ->where('status', PracticeAccountingConnection::STATUS_CONNECTED)
            ->whereNull('revoked_at')
            ->latest('connected_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function freshToken(PracticeAccountingConnection $connection): array
    {
        $token = $this->decryptToken($connection);
        $expiresAt = is_string($token['expires_at'] ?? null) ? Carbon::parse($token['expires_at']) : null;

        if (! $expiresAt instanceof Carbon || $expiresAt->greaterThan(now()->addMinutes(2))) {
            return $token;
        }

        $refreshed = $this->xero->refreshAccessToken($token);
        $tokenEnvelope = $this->envelope->encrypt($this->json($refreshed));

        $connection->forceFill([
            'token_envelope' => $tokenEnvelope,
            'token_envelope_meta' => $this->envelope->inspect($tokenEnvelope),
            'scopes' => $refreshed['scopes'] ?? $connection->scopes,
        ])->save();

        return $refreshed;
    }

    public function revoke(PracticeAccountingConnection $connection, User $user): PracticeAccountingConnection
    {
        $token = $this->decryptToken($connection);
        $this->xero->revoke(new AccountingConnection([
            'provider' => $connection->provider,
            'external_tenant_id' => $connection->external_tenant_id,
        ]), $token);

        $connection->forceFill([
            'status' => PracticeAccountingConnection::STATUS_REVOKED,
            'revoked_at' => now(),
            'revoked_by_user_id' => $user->getKey(),
        ])->save();

        $this->audit->record('practice_accounting_connection.revoked', subject: $connection, actor: $user, after: [
            'provider' => $connection->provider,
            'external_tenant_id' => $connection->external_tenant_id,
        ]);

        return $connection->refresh();
    }

    /**
     * @return array<int, string>
     */
    public function scopes(string $provider): array
    {
        $this->assertProvider($provider);

        return [
            'accounting.invoices',
            'accounting.contacts',
            'offline_access',
        ];
    }

    private function callbackUrl(string $provider): string
    {
        return route('admin.practice-accounting.callback', $provider);
    }

    private function assertProvider(string $provider): void
    {
        if ($provider !== AccountingConnection::PROVIDER_XERO) {
            throw new InvalidArgumentException("Practice accounting provider [{$provider}] is not supported yet.");
        }
    }

    private function stateFor(string $provider, User $user): string
    {
        $payload = $this->json([
            'provider' => $provider,
            'purpose' => 'practice_accounting',
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
            && (string) ($payload['purpose'] ?? '') === 'practice_accounting'
            && (string) ($payload['user_id'] ?? '') === (string) $user->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    private function decryptToken(PracticeAccountingConnection $connection): array
    {
        try {
            $decoded = json_decode($this->envelope->decrypt($connection->token_envelope), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Stored practice accounting token is not valid JSON.', previous: $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function json(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Unable to encode practice accounting payload.', previous: $e);
        }
    }
}
