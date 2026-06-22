<?php

declare(strict_types=1);

namespace App\Mail\Transport;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

final class MicrosoftGraphTransport extends AbstractTransport
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $from = $this->requiredEmail('from_address');
        $response = $this->sendMessage($message, $from);

        if (in_array($response->status(), [401, 403], true)) {
            Cache::forget($this->tokenCacheKey());
            $response = $this->sendMessage($message, $from, refreshToken: true);
        }

        if (! $response->successful()) {
            throw new TransportException('Microsoft Graph sendMail failed: '.$this->failureMessage($response));
        }

        $message->appendDebug('Microsoft Graph sendMail accepted the message.');
    }

    public function __toString(): string
    {
        return 'graph';
    }

    private function sendMessage(SentMessage $message, string $from, bool $refreshToken = false): Response
    {
        $endpoint = $this->baseUrl().'/users/'.rawurlencode($from).'/sendMail';

        return Http::withToken($this->accessToken($refreshToken))
            ->acceptJson()
            ->timeout($this->timeout())
            ->withBody(base64_encode($message->toString()), 'text/plain')
            ->post($endpoint);
    }

    private function accessToken(bool $refresh = false): string
    {
        $cacheKey = $this->tokenCacheKey();
        $cached = Cache::get($cacheKey);
        if (! $refresh && is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout($this->timeout())
            ->post($this->tokenUrl(), [
                'client_id' => $this->requiredString('client_id'),
                'client_secret' => $this->requiredString('client_secret'),
                'grant_type' => 'client_credentials',
                'scope' => $this->scope(),
            ]);

        if (! $response->successful()) {
            throw new TransportException('Microsoft Graph token request failed: '.$this->failureMessage($response));
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new TransportException('Microsoft Graph token request did not return an access token.');
        }

        $ttl = max(60, ((int) $response->json('expires_in', 3600)) - 120);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    private function tokenCacheKey(): string
    {
        return 'mail:graph:token:'.sha1($this->tenant().'|'.$this->requiredString('client_id').'|'.$this->scope());
    }

    private function tokenUrl(): string
    {
        $url = $this->stringValue('token_url', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token');

        return str_replace('{tenant}', rawurlencode($this->tenant()), $url);
    }

    private function tenant(): string
    {
        return $this->requiredString('tenant');
    }

    private function baseUrl(): string
    {
        return rtrim($this->stringValue('base_url', 'https://graph.microsoft.com/v1.0'), '/');
    }

    private function scope(): string
    {
        return $this->stringValue('scope', 'https://graph.microsoft.com/.default');
    }

    private function timeout(): int
    {
        $timeout = (int) ($this->config['timeout'] ?? 15);

        return max(1, $timeout);
    }

    private function requiredString(string $key): string
    {
        $value = trim((string) ($this->config[$key] ?? ''));
        if ($value === '') {
            throw new TransportException("Microsoft Graph mailer is missing {$key}.");
        }

        return $value;
    }

    private function requiredEmail(string $key): string
    {
        $value = $this->requiredString($key);
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new TransportException("Microsoft Graph mailer {$key} must be a valid mailbox email address.");
        }

        return $value;
    }

    private function stringValue(string $key, string $default): string
    {
        $value = trim((string) ($this->config[$key] ?? ''));

        return $value === '' ? $default : $value;
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
