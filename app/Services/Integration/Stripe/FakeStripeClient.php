<?php

declare(strict_types=1);

namespace App\Services\Integration\Stripe;

use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentChargeLookup;
use App\Services\Payments\DefinitivePaymentDecline;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentSetupIntent;
use Illuminate\Support\Arr;

final class FakeStripeClient implements StripeClient
{
    /** @var array<string, PaymentChargeResult> */
    private array $charges = [];
    public function createSetupIntent(PaymentAuthorityRequest $request): PaymentSetupIntent
    {
        if ($this->shouldFail($request)) {
            throw new PaymentGatewayException('Stripe fixture setup failed.');
        }

        $hash = substr(hash('sha256', json_encode($request->payload, JSON_THROW_ON_ERROR)), 0, 16);

        return new PaymentSetupIntent(
            publishableKey: 'pk_test_fixture',
            clientSecret: 'seti_fixture_'.$hash.'_secret_fixture',
            setupIntentRef: 'seti_fixture_'.$hash,
            customerRef: 'cus_stripe_'.substr($request->clientId, 0, 8),
            metadata: [
                'fixture' => true,
            ],
        );
    }

    public function captureAuthority(PaymentAuthorityRequest $request): PaymentAuthorityToken
    {
        if ($this->shouldFail($request)) {
            throw new PaymentGatewayException('Stripe fixture authority capture failed.');
        }

        $reference = (string) ($request->payload['payment_method_ref']
            ?? $request->payload['fixture_token']
            ?? '');
        $hash = substr(hash('sha256', $reference !== ''
            ? $reference
            : json_encode($request->payload, JSON_THROW_ON_ERROR)), 0, 16);

        return new PaymentAuthorityToken(
            token: 'tok_stripe_'.$hash,
            customerRef: (string) ($request->payload['customer_ref'] ?? 'cus_stripe_'.substr($request->clientId, 0, 8)),
            metadata: [
                'gateway' => 'stripe',
                'fixture' => true,
                'type' => $request->type,
                'setup_intent_ref' => $request->payload['setup_intent_ref'] ?? null,
            ],
        );
    }

    public function charge(PaymentChargeRequest $request): PaymentChargeResult
    {
        if ((bool) Arr::get($request->metadata, 'fixture_fail', false) || (bool) Arr::get($request->metadata, 'fixture_fail_stripe', false)) {
            throw new DefinitivePaymentDecline('Stripe fixture charge failed.');
        }

        $hash = substr(hash('sha256', implode('|', [
            $request->authorityId,
            $request->amount,
            $request->currency,
            $request->idempotencyKey,
        ])), 0, 16);

        $result = new PaymentChargeResult(
            gateway: 'stripe',
            gatewayRef: 'ch_stripe_'.$hash,
            status: 'succeeded',
            amount: $request->amount,
            currency: $request->currency,
            metadata: [
                'fixture' => true,
                'customer_ref' => $request->customerRef,
            ],
        );
        $this->charges[$request->idempotencyKey] = $result;

        return $result;
    }

    public function findCharge(?string $gatewayRef, string $idempotencyKey, string $paymentId): PaymentChargeLookup
    {
        $charge = $this->charges[$idempotencyKey] ?? null;

        return $charge instanceof PaymentChargeResult
            ? PaymentChargeLookup::succeeded($charge)
            : PaymentChargeLookup::unknown();
    }

    private function shouldFail(PaymentAuthorityRequest $request): bool
    {
        return (bool) Arr::get($request->payload, 'fixture_fail', false)
            || Arr::get($request->payload, 'fixture_token') === 'fail';
    }
}
