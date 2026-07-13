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
use App\Services\Payments\PaymentChargeLookup;
use App\Services\Payments\AmbiguousPaymentOutcome;
use App\Services\Payments\DefinitivePaymentDecline;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentSetupIntent;
use Illuminate\Support\Facades\Config;

final class LiveStripeClient implements StripeClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly IntegrationActivationResolver $live,
        private readonly IntegrationCredentials $credentials,
    ) {}

    public function createSetupIntent(PaymentAuthorityRequest $request): PaymentSetupIntent
    {
        $secret = $this->secret();
        $publishableKey = $this->publishableKey();
        $customerRef = $this->customerRef($request, $secret);

        $result = $this->http->request(
            method: 'POST',
            service: 'stripe',
            endpoint: $this->endpoint('/v1/setup_intents'),
            options: [
                'headers' => $this->headers($secret, 'setup-'.$request->proposalId.'-'.$request->type),
                'form_params' => $this->params([
                    'customer' => $customerRef,
                    'usage' => 'off_session',
                    'automatic_payment_methods' => [
                        'enabled' => 'true',
                    ],
                    'metadata' => $this->metadata([
                        'client_id' => $request->clientId,
                        'proposal_id' => $request->proposalId,
                        'type' => $request->type,
                    ]),
                ]),
            ],
        );

        if (! $result->successful() || $result->fromFallback || ! is_array($result->data)) {
            throw new PaymentGatewayException($this->stripeFailureMessage($result->data, 'Stripe payment setup could not be started.'));
        }

        $clientSecret = (string) data_get($result->data, 'client_secret', '');
        $setupIntentRef = (string) data_get($result->data, 'id', '');

        if ($clientSecret === '' || $setupIntentRef === '') {
            throw new PaymentGatewayException('Stripe payment setup did not return the details needed to collect card details.');
        }

        return new PaymentSetupIntent(
            publishableKey: $publishableKey,
            clientSecret: $clientSecret,
            setupIntentRef: $setupIntentRef,
            customerRef: $customerRef,
            metadata: [
                'gateway' => 'stripe',
                'live' => true,
                'correlation_id' => $result->correlationId,
            ],
        );
    }

    public function captureAuthority(PaymentAuthorityRequest $request): PaymentAuthorityToken
    {
        $secret = $this->secret();
        $setupIntentRef = $this->payloadString($request->payload, 'setup_intent_ref');

        if ($setupIntentRef !== null) {
            return $this->captureConfirmedSetupIntent($request, $secret, $setupIntentRef);
        }

        $customerRef = $this->customerRef($request, $secret);
        $result = $this->http->request(
            method: 'POST',
            service: 'stripe',
            endpoint: $this->endpoint('/v1/setup_intents'),
            options: [
                'headers' => $this->headers($secret, 'authority-'.$request->proposalId),
                'form_params' => $this->params([
                    'customer' => $customerRef,
                    'payment_method' => $request->payload['payment_method_ref'] ?? $request->payload['fixture_token'] ?? null,
                    'confirm' => 'true',
                    'usage' => 'off_session',
                    'metadata' => $this->metadata([
                        'client_id' => $request->clientId,
                        'proposal_id' => $request->proposalId,
                        'type' => $request->type,
                    ]),
                ]),
            ],
        );

        if (! $result->successful() || $result->fromFallback || ! is_array($result->data)) {
            throw new PaymentGatewayException($this->stripeFailureMessage($result->data, 'Stripe authority capture failed.'));
        }

        $status = (string) data_get($result->data, 'status', '');

        if ($status !== 'succeeded') {
            throw new PaymentGatewayException('Stripe payment setup is incomplete. Please check the card details and try again.');
        }

        $token = (string) data_get($result->data, 'payment_method', '');

        if ($token === '') {
            throw new PaymentGatewayException('Stripe authority capture did not return a token.');
        }

        return new PaymentAuthorityToken(
            token: $token,
            customerRef: (string) data_get($result->data, 'customer', ''),
            metadata: [
                'gateway' => 'stripe',
                'live' => true,
                'setup_intent_ref' => (string) data_get($result->data, 'id', ''),
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
                'form_params' => $this->params([
                    'amount' => (int) round(((float) $request->amount) * 100),
                    'currency' => strtolower($request->currency),
                    'customer' => $request->customerRef,
                    'payment_method' => $request->token,
                    'confirm' => 'true',
                    'off_session' => 'true',
                    'metadata' => $this->metadata([
                        'client_id' => $request->clientId,
                        'proposal_id' => $request->proposalId,
                        'payment_authority_id' => $request->authorityId,
                    ], $request->metadata),
                ]),
            ],
        );

        if (! $result->successful() || $result->fromFallback || ! is_array($result->data)) {
            throw new AmbiguousPaymentOutcome('Stripe charge could not be confirmed.');
        }

        $status = (string) data_get($result->data, 'status', 'succeeded');

        if (! in_array($status, ['succeeded', 'processing'], true)) {
            throw new DefinitivePaymentDecline('Stripe charge was declined.');
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

    public function findCharge(?string $gatewayRef, string $idempotencyKey, string $paymentId): PaymentChargeLookup
    {
        if ($gatewayRef === null || $gatewayRef === '') {
            return PaymentChargeLookup::unknown();
        }

        $result = $this->http->request(
            method: 'GET',
            service: 'stripe',
            endpoint: $this->endpoint('/v1/payment_intents/'.rawurlencode($gatewayRef)),
            options: ['headers' => $this->headers($this->secret())],
        );
        if (! $result->successful() || $result->fromFallback || ! is_array($result->data)) {
            return PaymentChargeLookup::unknown();
        }

        $status = (string) data_get($result->data, 'status', '');
        if ($status === 'succeeded') {
            return PaymentChargeLookup::succeeded(new PaymentChargeResult(
                gateway: 'stripe',
                gatewayRef: (string) data_get($result->data, 'id', $gatewayRef),
                status: $status,
                amount: number_format(((float) data_get($result->data, 'amount_received', data_get($result->data, 'amount', 0))) / 100, 2, '.', ''),
                currency: strtoupper((string) data_get($result->data, 'currency', 'NZD')),
            ));
        }

        return in_array($status, ['canceled', 'requires_payment_method'], true)
            ? PaymentChargeLookup::notCharged()
            : PaymentChargeLookup::unknown();
    }

    private function captureConfirmedSetupIntent(
        PaymentAuthorityRequest $request,
        string $secret,
        string $setupIntentRef,
    ): PaymentAuthorityToken {
        $result = $this->http->request(
            method: 'GET',
            service: 'stripe',
            endpoint: $this->endpoint('/v1/setup_intents/'.rawurlencode($setupIntentRef)),
            options: [
                'headers' => $this->headers($secret),
            ],
        );

        if (! $result->successful() || $result->fromFallback || ! is_array($result->data)) {
            throw new PaymentGatewayException($this->stripeFailureMessage($result->data, 'Stripe payment setup could not be verified.'));
        }

        $status = (string) data_get($result->data, 'status', '');

        if ($status !== 'succeeded') {
            throw new PaymentGatewayException('Stripe payment setup is incomplete. Please check the card details and try again.');
        }

        $paymentMethodRef = (string) data_get($result->data, 'payment_method', '');
        $customerRef = (string) data_get($result->data, 'customer', '');

        if ($paymentMethodRef === '') {
            throw new PaymentGatewayException('Stripe payment setup did not return a payment method.');
        }

        $submittedPaymentMethod = $this->payloadString($request->payload, 'payment_method_ref');
        if ($submittedPaymentMethod !== null && $submittedPaymentMethod !== $paymentMethodRef) {
            throw new PaymentGatewayException('Stripe payment method verification failed. Please re-enter the card details and try again.');
        }

        $submittedCustomerRef = $this->payloadString($request->payload, 'customer_ref');
        if ($submittedCustomerRef !== null && $customerRef !== '' && $submittedCustomerRef !== $customerRef) {
            throw new PaymentGatewayException('Stripe customer verification failed. Please re-enter the card details and try again.');
        }

        return new PaymentAuthorityToken(
            token: $paymentMethodRef,
            customerRef: $customerRef,
            metadata: [
                'gateway' => 'stripe',
                'live' => true,
                'setup_intent_ref' => $setupIntentRef,
                'correlation_id' => $result->correlationId,
            ],
        );
    }

    private function customerRef(PaymentAuthorityRequest $request, string $secret): string
    {
        $existing = $this->payloadString($request->payload, 'customer_ref');
        if ($existing !== null) {
            return $existing;
        }

        $result = $this->http->request(
            method: 'POST',
            service: 'stripe',
            endpoint: $this->endpoint('/v1/customers'),
            options: [
                'headers' => $this->headers($secret, 'customer-'.$request->clientId),
                'form_params' => $this->params([
                    'email' => $this->payloadString($request->payload, 'customer_email'),
                    'name' => $this->payloadString($request->payload, 'customer_name'),
                    'metadata' => $this->metadata([
                        'client_id' => $request->clientId,
                    ]),
                ]),
            ],
        );

        if (! $result->successful() || $result->fromFallback || ! is_array($result->data)) {
            throw new PaymentGatewayException($this->stripeFailureMessage($result->data, 'Stripe customer could not be created.'));
        }

        $customerRef = (string) data_get($result->data, 'id', '');

        if ($customerRef === '') {
            throw new PaymentGatewayException('Stripe customer setup did not return a customer reference.');
        }

        return $customerRef;
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

    private function publishableKey(): string
    {
        $publishableKey = (string) ($this->credentials->get('stripe', 'publishable_key') ?? '');

        if ($publishableKey === '') {
            throw new PaymentGatewayException('Stripe publishable key is not configured. Add it to Integration credentials before collecting card details.');
        }

        return $publishableKey;
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $secret, ?string $idempotencyKey = null): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$secret,
            'Accept' => 'application/json',
        ];

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) Config::get('integrations.payments.stripe.base_url', 'https://api.stripe.com'), '/').$path;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function params(array $params): array
    {
        return array_filter($params, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function stripeFailureMessage(mixed $payload, string $fallback): string
    {
        $message = is_array($payload) ? data_get($payload, 'error.message') : null;

        return is_string($message) && trim($message) !== ''
            ? trim($message)
            : $fallback;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $extra
     * @return array<string, string>
     */
    private function metadata(array $base, array $extra = []): array
    {
        $metadata = [];

        foreach ([...$base, ...$extra] as $key => $value) {
            if (! is_scalar($value) || $value === '') {
                continue;
            }

            $metadata[(string) $key] = is_bool($value)
                ? ($value ? 'true' : 'false')
                : (string) $value;
        }

        return $metadata;
    }
}
