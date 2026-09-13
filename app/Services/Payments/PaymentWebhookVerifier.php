<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Services\Integration\IntegrationCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

final class PaymentWebhookVerifier
{
    public function __construct(private readonly IntegrationCredentials $credentials) {}

    /**
     * @return array{0: bool, 1: string|null}
     */
    public function verifyStripe(Request $request): array
    {
        $secret = (string) ($this->credentials->get('stripe', 'webhook_secret') ?? '');

        if ($secret === '') {
            return [false, 'secret_not_configured'];
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', (string) $request->header('Stripe-Signature', '')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && $timestamp === null) {
                $timestamp = $value;
            }

            // Stripe includes one v1 signature for each active endpoint secret
            // while a signing secret is being rotated. Accept the one generated
            // with the secret configured for this application.
            if ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if (! is_string($timestamp) || ! ctype_digit($timestamp) || $signatures === []) {
            return [false, 'signature_missing'];
        }

        if ($this->timestampOutOfWindow((int) $timestamp)) {
            return [false, 'timestamp_out_of_window'];
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return [true, null];
            }
        }

        return [false, 'signature_mismatch'];
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    public function verifyWindcave(Request $request): array
    {
        $secret = (string) ($this->credentials->get('windcave', 'webhook_secret') ?? '');

        if ($secret === '') {
            return [false, 'secret_not_configured'];
        }

        $timestamp = (string) $request->header('X-Windcave-Timestamp', '');
        $signature = (string) $request->header('X-Windcave-Signature', '');

        if ($timestamp === '' || ! ctype_digit($timestamp) || $signature === '') {
            return [false, 'signature_missing'];
        }

        if ($this->timestampOutOfWindow((int) $timestamp)) {
            return [false, 'timestamp_out_of_window'];
        }

        $provided = Str::startsWith($signature, 'sha256=')
            ? Str::after($signature, 'sha256=')
            : $signature;
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $provided)
            ? [true, null]
            : [false, 'signature_mismatch'];
    }

    private function timestampOutOfWindow(int $timestamp): bool
    {
        $tolerance = max(1, (int) Config::get('integrations.payments.webhook_tolerance_seconds', 300));

        return abs(now()->getTimestamp() - $timestamp) > $tolerance;
    }
}
