<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies a Cloudflare Turnstile token for the public contact form.
 *
 * Until a secret is configured this no-ops (reports verified) so the form keeps
 * working; once TURNSTILE_SECRET_KEY is set the check is enforced and fails
 * closed. The call to Cloudflare goes through the resilience layer, never a raw
 * HTTP client, per the integration rules.
 */
final class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private readonly ResilientHttp $http) {}

    public function isConfigured(): bool
    {
        return $this->secret() !== '';
    }

    public function verify(?string $token, ?string $ip): bool
    {
        if (! $this->isConfigured()) {
            return true;
        }

        if ($token === null || trim($token) === '') {
            return false;
        }

        try {
            $result = $this->http->post('turnstile', self::VERIFY_URL, array_filter([
                'secret' => $this->secret(),
                'response' => trim($token),
                'remoteip' => $ip,
            ], static fn ($value): bool => $value !== null && $value !== ''));

            return $result->successful() && $result->json('success') === true;
        } catch (Throwable $e) {
            Log::warning('Turnstile verification failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function secret(): string
    {
        return trim((string) config('services.turnstile.secret_key'));
    }
}
