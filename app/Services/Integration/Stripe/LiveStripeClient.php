<?php

declare(strict_types=1);

namespace App\Services\Integration\Stripe;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Support\Facades\Config;

final class LiveStripeClient implements StripeClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly IntegrationActivationResolver $live,
        private readonly IntegrationCredentials $credentials,
    ) {}

    public function captureAuthority(PaymentAuthorityRequest $request): PaymentAuthorityToken
    {
        $secret = $this->secret();

        $result = $this->http->request(
            method: 'POST',
            service: 'stripe',
            endpoint: $this->endpoint('/v1/setup_intents'),
            options: [
                'headers' => $this->headers($secret, 'authority-'.$request->proposalId),
                'json' => [
                    'customer' => $request->payload['customer_ref'] ?? null,
                    'payment_method' => $request->payload['payment_method_ref'] ?? $request->payload['fixture_token'] ?? null,
                    'usage' => 'off_session',
                    'metadata' => [
                        'client_id' => $request->clientId,
                        'proposal_id' => $request->proposalId,
                        'type' => $request->type,
                    ],
                ],
            ],
        );

        if (! $result->successful() || $result->fromFallback || ! is_array($result->data)) {
            throw new PaymentGatewayException('Stripe authority capture failed.');
        }

        $token = (string) data_get($result->data, 'payment_method', data_get($result->data, 'id', ''));

        if ($token === '') {
            throw new PaymentGatewayException('Stripe authority capture did not return a token.');
        }

        return new PaymentAuthorityToken(
            token: $token,
            customerRef: (string) data_get($result->data, 'customer', ''),
            metadata: [
                'gateway' => 'stripe',
                'live' => true,
                'correlation_id' => $result->correlationId,
            ],
        );
    }

    public function charge(PaymentChargeRequest $request): PaymentChargeResult
    {
        $secret = $this->secret();

        $result = $this->http->request(
            method: 'POST',
            service: 'stripe',
            endpoint: $this->endpoint('/v1/payment_intents'),
            options: [
                'headers' => $this->headers($secret, $request->idempotencyKey),
                'json' => [
                    'amount' => (int) round(((float) $request->amount) * 100),
                    'currency' => strtolower($request->currency),
                    'customer' => $request->customerRef,
                    'payment_method' => $request->token,
                    'confirm' => true,
                    'off_session' => true,
                    'metadata' => [
                        'client_id' => $request->clientId,
                        'proposal_id' => $request->proposalId,
                        'payment_authority_id' => $request->authorityId,
                    ],
                ],
            ],
        );

        if (! $result->successful() || $result->fromFallback || ! is_array($result->data)) {
            throw new PaymentGatewayException('Stripe charge failed.');
        }

        $status = (string) data_get($result->data, 'status', 'succeeded');

        if (! in_array($status, ['succeeded', 'processing'], true)) {
            throw new PaymentGatewayException('Stripe charge did not succeed.');
        }

        return new PaymentChargeResult(
            gateway: 'stripe',
            gatewayRef: (string) data_get($result->data, 'id', ''),
            status: $status,
            amount: $request->amount,
            currency: $request->currency,
            metadata: [
                'live' => true,
                'correlation_id' => $result->correlationId,
            ],
        );
    }

    private function secret(): string
    {
        if (! $this->live->isLive('stripe')) {
            throw IntegrationDisabledException::forService('stripe');
        }

        $secret = (string) ($this->credentials->get('stripe', 'secret') ?? '');

        if ($secret === '') {
            throw new PaymentGatewayException('Stripe secret is not configured.');
        }

        return $secret;
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $secret, string $idempotencyKey): array
    {
        return [
            'Authorization' => 'Bearer '.$secret,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) Config::get('integrations.payments.stripe.base_url', 'https://api.stripe.com'), '/').$path;
    }
}
