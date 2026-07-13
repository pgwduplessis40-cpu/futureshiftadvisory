<?php

declare(strict_types=1);

namespace App\Services\Integration\Windcave;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Windcave\Contracts\WindcaveClient;
use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentChargeLookup;
use App\Services\Payments\AmbiguousPaymentOutcome;
use App\Services\Payments\DefinitivePaymentDecline;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Support\Facades\Config;

final class LiveWindcaveClient implements WindcaveClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly IntegrationActivationResolver $live,
        private readonly IntegrationCredentials $credentials,
    ) {}

    public function captureAuthority(PaymentAuthorityRequest $request): PaymentAuthorityToken
    {
        $result = $this->http->request(
            method: 'POST',
            service: 'windcave',
            endpoint: $this->endpoint('/v1/authorities'),
            options: [
                'headers' => $this->headers('authority-'.$request->proposalId),
                'json' => [
                    'customerRef' => $request->payload['customer_ref'] ?? null,
                    'token' => $request->payload['payment_method_ref'] ?? $request->payload['fixture_token'] ?? null,
                    'type' => $request->type,
                    'metadata' => [
                        'client_id' => $request->clientId,
                        'proposal_id' => $request->proposalId,
                    ],
                ],
            ],
        );

        if (! $result->successful() || $result->fromFallback || ! is_array($result->data)) {
            throw new PaymentGatewayException('Windcave authority capture failed.');
        }

        $token = (string) data_get($result->data, 'token', data_get($result->data, 'id', ''));

        if ($token === '') {
            throw new PaymentGatewayException('Windcave authority capture did not return a token.');
        }

        return new PaymentAuthorityToken(
            token: $token,
            customerRef: (string) data_get($result->data, 'customerRef', ''),
            metadata: [
                'gateway' => 'windcave',
                'live' => true,
                'correlation_id' => $result->correlationId,
            ],
        );
    }

    public function charge(PaymentChargeRequest $request): PaymentChargeResult
    {
        $result = $this->http->request(
            method: 'POST',
            service: 'windcave',
            endpoint: $this->endpoint('/v1/transactions'),
            options: [
                'headers' => $this->headers($request->idempotencyKey),
                'json' => [
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'customerRef' => $request->customerRef,
                    'token' => $request->token,
                    'capture' => true,
                    'metadata' => [
                        'client_id' => $request->clientId,
                        'proposal_id' => $request->proposalId,
                        'payment_authority_id' => $request->authorityId,
                    ],
                ],
            ],
        );

        if (! $result->successful() || $result->fromFallback || ! is_array($result->data)) {
            throw new AmbiguousPaymentOutcome('Windcave charge could not be confirmed.');
        }

        $status = (string) data_get($result->data, 'status', data_get($result->data, 'authorized', false) ? 'succeeded' : 'failed');

        if (! in_array($status, ['succeeded', 'approved', 'authorized'], true)) {
            throw new DefinitivePaymentDecline('Windcave charge was declined.');
        }

        return new PaymentChargeResult(
            gateway: 'windcave',
            gatewayRef: (string) data_get($result->data, 'transactionId', data_get($result->data, 'id', '')),
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
            service: 'windcave',
            endpoint: $this->endpoint('/v1/transactions/'.rawurlencode($gatewayRef)),
            options: ['headers' => $this->headers('lookup-'.$idempotencyKey)],
        );
        if (! $result->successful() || $result->fromFallback || ! is_array($result->data)) {
            return PaymentChargeLookup::unknown();
        }

        $status = strtolower((string) data_get($result->data, 'status', ''));
        if (in_array($status, ['succeeded', 'approved', 'authorized'], true)) {
            return PaymentChargeLookup::succeeded(new PaymentChargeResult(
                gateway: 'windcave',
                gatewayRef: (string) data_get($result->data, 'transactionId', data_get($result->data, 'id', $gatewayRef)),
                status: $status,
                amount: number_format((float) data_get($result->data, 'amount', 0), 2, '.', ''),
                currency: strtoupper((string) data_get($result->data, 'currency', 'NZD')),
            ));
        }

        return in_array($status, ['failed', 'declined', 'voided'], true)
            ? PaymentChargeLookup::notCharged()
            : PaymentChargeLookup::unknown();
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $idempotencyKey): array
    {
        if (! $this->live->isLive('windcave')) {
            throw IntegrationDisabledException::forService('windcave');
        }

        $user = (string) ($this->credentials->get('windcave', 'api_user') ?? '');
        $key = (string) ($this->credentials->get('windcave', 'api_key') ?? '');

        if ($user === '' || $key === '') {
            throw new PaymentGatewayException('Windcave credentials are not configured.');
        }

        return [
            'Authorization' => 'Basic '.base64_encode($user.':'.$key),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) Config::get('integrations.payments.windcave.base_url', 'https://sec.windcave.com/api'), '/').$path;
    }
}
