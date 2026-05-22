<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\PaymentAuthority;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Integration\Windcave\Contracts\WindcaveClient;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use JsonException;

final class AuthorityCapture
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly WindcaveClient $windcave,
        private readonly KeyEnvelope $envelope,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function capture(Proposal $proposal, string $type, string $gateway, array $payload, User $actor): PaymentAuthority
    {
        $proposal->loadMissing('client');

        if (! in_array($type, PaymentAuthority::types(), true)) {
            throw new InvalidArgumentException('Unsupported payment authority type.');
        }

        if (! in_array($gateway, PaymentAuthority::gateways(), true)) {
            throw new InvalidArgumentException('Unsupported payment gateway.');
        }

        $this->assertNoRawPan($payload);

        $request = new PaymentAuthorityRequest(
            clientId: (string) $proposal->client_id,
            proposalId: (string) $proposal->getKey(),
            type: $type,
            gateway: $gateway,
            payload: $payload,
        );

        $token = $gateway === PaymentAuthority::GATEWAY_STRIPE
            ? $this->stripe->captureAuthority($request)
            : $this->windcave->captureAuthority($request);

        $envelope = $this->envelope->encrypt($this->tokenPayload($token));

        $authority = PaymentAuthority::query()->create([
            'client_id' => $proposal->client_id,
            'proposal_id' => $proposal->getKey(),
            'type' => $type,
            'gateway' => $gateway,
            'gateway_customer_ref' => $token->customerRef,
            'gateway_token_envelope' => $envelope,
            'status' => PaymentAuthority::STATUS_ACTIVE,
            'authorised_by_user_id' => $actor->getKey(),
            'authorised_at' => now(),
        ]);

        $this->audit->record('payment_authority.captured', subject: $authority, actor: $actor, after: [
            'proposal_id' => $proposal->getKey(),
            'type' => $type,
            'gateway' => $gateway,
            'gateway_customer_ref' => $token->customerRef,
        ]);

        return $authority->refresh();
    }

    private function tokenPayload(PaymentAuthorityToken $token): string
    {
        try {
            return json_encode([
                'token' => $token->token,
                'customer_ref' => $token->customerRef,
                'metadata' => $token->metadata,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Payment token payload could not be encoded.', previous: $e);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertNoRawPan(array $payload): void
    {
        foreach (Arr::dot($payload) as $key => $value) {
            $key = strtolower((string) $key);

            if (str_contains($key, 'card_number') || $key === 'pan' || str_ends_with($key, '.pan')) {
                throw new InvalidArgumentException('Raw card numbers must not be submitted or stored.');
            }

            if (! is_scalar($value)) {
                continue;
            }

            $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

            if (strlen($digits) >= 13 && strlen($digits) <= 19 && str_contains($key, 'card')) {
                throw new InvalidArgumentException('Raw card numbers must not be submitted or stored.');
            }
        }
    }
}
