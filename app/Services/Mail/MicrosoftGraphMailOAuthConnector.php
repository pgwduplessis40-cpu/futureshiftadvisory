<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\MailOAuthConnection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Settings\ProjectSettings;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Mailer\Exception\TransportException;

final class MicrosoftGraphMailOAuthConnector
{
    /**
     * The mail sender can reuse the same Microsoft app registration as calendar
     * sync once the mail redirect URI and delegated Mail.Send permission are
     * added in Azure.
     *
     * @var array<string, array<int, string>>
     */
    private const FALLBACK_CONFIG_PATHS = [
        'tenant' => ['integrations.calendar.microsoft.tenant'],
        'client_id' => ['integrations.calendar.microsoft.client_id'],
        'client_secret' => ['integrations.calendar.microsoft.client_secret'],
        'authorize_url' => ['integrations.calendar.microsoft.authorize_url'],
        'token_url' => ['integrations.calendar.microsoft.token_url'],
    ];

    public function __construct(
        private readonly KeyEnvelope $envelope,
        private readonly AuditWriter $audit,
        private readonly ProjectSettings $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function statusPayload(): array
    {
        $connection = $this->connectedConnection();

        return [
            'available' => $this->tableAvailable(),
            'connected' => $connection instanceof MailOAuthConnection && $connection->connected(),
            'status' => $connection?->status,
            'mailbox_email' => $connection?->mailbox_email,
            'token_expires_at' => $connection?->token_expires_at?->toIso8601String(),
            'connected_at' => $connection?->connected_at?->toIso8601String(),
            'connected_by' => $connection?->connectedBy?->name,
            'last_error' => $connection?->last_error,
        ];
    }

    public function authorizeUrl(User $user, ?string $callbackUrl = null): string
    {
        $query = http_build_query(array_filter([
            'client_id' => $this->requiredConfig('client_id'),
            'redirect_uri' => $this->callbackUrl($callbackUrl),
            'response_type' => 'code',
            'response_mode' => 'query',
            'scope' => implode(' ', $this->delegatedScopes()),
            'prompt' => 'select_account',
            'login_hint' => $this->loginHint(),
            'state' => $this->stateFor($user),
        ], fn (mixed $value): bool => $value !== null && $value !== ''));

        return rtrim($this->authorizeUrlBase(), '?').'?'.$query;
    }

    public function connectFromCallback(User $user, string $code, string $state, ?string $callbackUrl = null): MailOAuthConnection
    {
        abort_unless($this->tableAvailable(), 503, 'Mail OAuth connection store is not migrated.');

        if (! $this->stateIsValid($state, $user)) {
            throw new InvalidArgumentException('Invalid Microsoft Graph mail OAuth state.');
        }

        $token = $this->exchangeAuthorizationCode($code, $callbackUrl);
        $accessToken = $this->requiredTokenValue($token, 'access_token');
        $refreshToken = $this->requiredTokenValue($token, 'refresh_token');
        $profile = $this->mailboxProfile($accessToken);
        $mailboxEmail = $this->mailboxEmail($profile);
        $externalAccountId = trim((string) ($profile['id'] ?? $mailboxEmail));

        $accessEnvelope = $this->envelope->encrypt($accessToken);
        $refreshEnvelope = $this->envelope->encrypt($refreshToken);

        /** @var MailOAuthConnection $connection */
        $connection = DB::transaction(function () use ($accessEnvelope, $externalAccountId, $mailboxEmail, $profile, $refreshEnvelope, $token, $user): MailOAuthConnection {
            MailOAuthConnection::query()
                ->where('provider', MailOAuthConnection::PROVIDER_MICROSOFT_GRAPH)
                ->where('status', MailOAuthConnection::STATUS_CONNECTED)
                ->update([
                    'status' => MailOAuthConnection::STATUS_REVOKED,
                    'revoked_by_user_id' => $user->getKey(),
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            $connection = MailOAuthConnection::query()->create([
                'provider' => MailOAuthConnection::PROVIDER_MICROSOFT_GRAPH,
                'mailbox_email' => $mailboxEmail,
                'external_account_id' => $externalAccountId,
                'status' => MailOAuthConnection::STATUS_CONNECTED,
                'access_token_envelope' => $accessEnvelope,
                'access_token_envelope_meta' => $this->envelope->inspect($accessEnvelope),
                'refresh_token_envelope' => $refreshEnvelope,
                'refresh_token_envelope_meta' => $this->envelope->inspect($refreshEnvelope),
                'token_expires_at' => now()->addSeconds(max(60, (int) ($token['expires_in'] ?? 3600))),
                'connected_by_user_id' => $user->getKey(),
                'connected_at' => now(),
                'last_error' => null,
            ]);

            $this->audit->record('mail_oauth_connection.connected', subject: $connection, actor: $user, after: [
                'provider' => MailOAuthConnection::PROVIDER_MICROSOFT_GRAPH,
                'mailbox_email' => $mailboxEmail,
                'external_account_id' => $externalAccountId,
                'user_principal_name' => $profile['userPrincipalName'] ?? null,
            ]);

            return $connection;
        });

        $this->applyConnectedMailSettings($connection, $user);

        return $connection->refresh();
    }

    public function accessToken(bool $forceRefresh = false): string
    {
        $connection = $this->connectedConnection();

        if (! $connection instanceof MailOAuthConnection || ! $connection->connected()) {
            throw new TransportException('Microsoft Graph delegated mail is not connected. Connect a mailbox in Project Settings.');
        }

        if (
            ! $forceRefresh
            && $connection->token_expires_at !== null
            && $connection->token_expires_at->isAfter(now()->addMinutes(5))
        ) {
            return $this->envelope->decrypt($connection->access_token_envelope);
        }

        return $this->refreshAccessToken($connection);
    }

    public function connectedMailbox(): ?string
    {
        $connection = $this->connectedConnection();

        return $connection instanceof MailOAuthConnection && $connection->connected()
            ? $connection->mailbox_email
            : null;
    }

    public function disconnect(User $user): ?MailOAuthConnection
    {
        $connection = $this->connectedConnection();
        if (! $connection instanceof MailOAuthConnection) {
            return null;
        }

        $connection->forceFill([
            'status' => MailOAuthConnection::STATUS_REVOKED,
            'revoked_by_user_id' => $user->getKey(),
            'revoked_at' => now(),
        ])->save();

        $this->audit->record('mail_oauth_connection.revoked', subject: $connection, actor: $user, after: [
            'provider' => $connection->provider,
            'mailbox_email' => $connection->mailbox_email,
        ]);

        return $connection->refresh();
    }

    private function refreshAccessToken(MailOAuthConnection $connection): string
    {
        $refreshToken = $connection->refresh_token_envelope === null
            ? ''
            : $this->envelope->decrypt($connection->refresh_token_envelope);

        if ($refreshToken === '') {
            throw new TransportException('Microsoft Graph delegated mail refresh token is missing. Reconnect the mailbox.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout($this->timeout())
            ->post($this->tokenUrl(), array_filter([
                'client_id' => $this->requiredConfig('client_id'),
                'client_secret' => $this->requiredConfig('client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'scope' => implode(' ', $this->delegatedScopes()),
            ], fn (mixed $value): bool => $value !== null && $value !== ''));

        if (! $response->successful()) {
            $connection->forceFill([
                'status' => MailOAuthConnection::STATUS_ERROR,
                'last_error' => $this->failureMessage($response),
            ])->save();

            throw new TransportException('Microsoft Graph delegated token refresh failed: '.$this->failureMessage($response));
        }

        $payload = $response->json();
        $accessToken = $this->requiredTokenValue(is_array($payload) ? $payload : [], 'access_token');
        $newRefreshToken = (string) $response->json('refresh_token', '');
        $accessEnvelope = $this->envelope->encrypt($accessToken);

        $updates = [
            'status' => MailOAuthConnection::STATUS_CONNECTED,
            'access_token_envelope' => $accessEnvelope,
            'access_token_envelope_meta' => $this->envelope->inspect($accessEnvelope),
            'token_expires_at' => now()->addSeconds(max(60, (int) $response->json('expires_in', 3600))),
            'last_error' => null,
        ];

        if ($newRefreshToken !== '') {
            $refreshEnvelope = $this->envelope->encrypt($newRefreshToken);
            $updates['refresh_token_envelope'] = $refreshEnvelope;
            $updates['refresh_token_envelope_meta'] = $this->envelope->inspect($refreshEnvelope);
        }

        $connection->forceFill($updates)->save();

        return $accessToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeAuthorizationCode(string $code, ?string $callbackUrl = null): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout($this->timeout())
            ->post($this->tokenUrl(), array_filter([
                'client_id' => $this->requiredConfig('client_id'),
                'client_secret' => $this->requiredConfig('client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->callbackUrl($callbackUrl),
                'scope' => implode(' ', $this->delegatedScopes()),
            ], fn (mixed $value): bool => $value !== null && $value !== ''));

        if (! $response->successful()) {
            throw new InvalidArgumentException('Microsoft Graph mail token exchange failed: '.$this->failureMessage($response));
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function mailboxProfile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout($this->timeout())
            ->get($this->baseUrl().'/me', [
                '$select' => 'id,mail,userPrincipalName',
            ]);

        if (! $response->successful()) {
            throw new InvalidArgumentException('Microsoft Graph mailbox lookup failed: '.$this->failureMessage($response));
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function mailboxEmail(array $profile): string
    {
        $email = trim((string) ($profile['mail'] ?? ''));
        if ($email === '') {
            $email = trim((string) ($profile['userPrincipalName'] ?? ''));
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Microsoft Graph did not return a usable mailbox email address.');
        }

        return $email;
    }

    /**
     * @param  array<string, mixed>  $token
     */
    private function requiredTokenValue(array $token, string $key): string
    {
        $value = trim((string) ($token[$key] ?? ''));

        if ($value === '') {
            $label = str_replace('_', ' ', $key);
            throw new InvalidArgumentException("Microsoft Graph did not return a {$label}. Make sure offline_access and Mail.Send delegated permissions are configured.");
        }

        return $value;
    }

    private function applyConnectedMailSettings(MailOAuthConnection $connection, User $user): void
    {
        $definitions = $this->settings->definitionsByKey();
        $updates = [
            'mail.default' => 'graph',
            'mail.mailers.graph.auth_mode' => 'delegated',
            'mail.mailers.graph.from_address' => $connection->mailbox_email,
        ];

        $fromAddress = trim((string) Config::get('mail.from.address', ''));
        if ($fromAddress === '' || $fromAddress === 'hello@example.com' || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            $updates['mail.from.address'] = $connection->mailbox_email;
        }

        foreach ($updates as $key => $value) {
            if (isset($definitions[$key])) {
                $this->settings->set($definitions[$key], $value, $user);
            }
        }
    }

    private function connectedConnection(): ?MailOAuthConnection
    {
        if (! $this->tableAvailable()) {
            return null;
        }

        return MailOAuthConnection::query()
            ->with('connectedBy')
            ->where('provider', MailOAuthConnection::PROVIDER_MICROSOFT_GRAPH)
            ->whereIn('status', [MailOAuthConnection::STATUS_CONNECTED, MailOAuthConnection::STATUS_ERROR])
            ->latest()
            ->first();
    }

    private function tableAvailable(): bool
    {
        try {
            return Schema::hasTable('mail_oauth_connections');
        } catch (\Throwable) {
            return false;
        }
    }

    private function callbackUrl(?string $callbackUrl = null): string
    {
        return $callbackUrl ?: route('admin.project-settings.mail-graph.callback');
    }

    private function tokenUrl(): string
    {
        return str_replace('{tenant}', rawurlencode($this->tenant()), $this->configString('token_url', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token'));
    }

    private function authorizeUrlBase(): string
    {
        return str_replace('{tenant}', rawurlencode($this->tenant()), $this->configString('authorize_url', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize'));
    }

    private function tenant(): string
    {
        return $this->requiredConfig('tenant');
    }

    private function baseUrl(): string
    {
        return rtrim($this->configString('base_url', 'https://graph.microsoft.com/v1.0'), '/');
    }

    private function loginHint(): ?string
    {
        $from = trim((string) Config::get('mail.mailers.graph.from_address', ''));

        return filter_var($from, FILTER_VALIDATE_EMAIL) !== false ? $from : null;
    }

    private function timeout(): int
    {
        return max(1, (int) Config::get('mail.mailers.graph.timeout', 15));
    }

    private function requiredConfig(string $key): string
    {
        $value = $this->configString($key, '');

        if ($value === '') {
            throw new InvalidArgumentException("Microsoft Graph mail OAuth is missing {$key}.");
        }

        return $value;
    }

    private function configString(string $key, string $default): string
    {
        $value = Config::get("mail.mailers.graph.{$key}", $default);
        $value = trim(is_array($value) ? implode(' ', $value) : (string) $value);

        if ($value !== '') {
            return $value;
        }

        foreach (self::FALLBACK_CONFIG_PATHS[$key] ?? [] as $path) {
            $fallback = Config::get($path, '');
            $fallback = trim(is_array($fallback) ? implode(' ', $fallback) : (string) $fallback);

            if ($fallback !== '') {
                return $fallback;
            }
        }

        return trim($default);
    }

    /**
     * @return array<int, string>
     */
    private function delegatedScopes(): array
    {
        $configured = Config::get('mail.mailers.graph.delegated_scopes');

        $scopes = is_array($configured)
            ? $configured
            : (preg_split('/[\s,]+/', (string) $configured) ?: []);

        $scopes = collect($scopes)
            ->map(fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->values()
            ->all();

        return $scopes === [] ? ['offline_access', 'User.Read', 'Mail.Send'] : $scopes;
    }

    private function stateFor(User $user): string
    {
        $payload = $this->json([
            'purpose' => 'graph_mail_oauth',
            'user_id' => (string) $user->getKey(),
        ]);
        $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, (string) Config::get('app.key'));

        return $encoded.'.'.$signature;
    }

    private function stateIsValid(string $state, User $user): bool
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
            && (string) ($payload['purpose'] ?? '') === 'graph_mail_oauth'
            && (string) ($payload['user_id'] ?? '') === (string) $user->getKey();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function json(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Unable to encode Microsoft Graph mail OAuth state.', previous: $exception);
        }
    }

    private function failureMessage(Response $response): string
    {
        $payload = $response->json();
        $message = null;

        if (is_array($payload)) {
            $message = data_get($payload, 'error.message')
                ?? data_get($payload, 'error_description')
                ?? data_get($payload, 'message');
        }

        $message = trim((string) ($message ?: $response->body()));
        if ($message === '') {
            $message = 'the provider returned an empty response.';
        }

        return 'HTTP '.$response->status().' - '.mb_substr($message, 0, 500);
    }
}
