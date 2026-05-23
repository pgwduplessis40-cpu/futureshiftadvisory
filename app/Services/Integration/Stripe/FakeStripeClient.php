<?php

declare(strict_types=1);

namespace App\Services\Integration\Stripe;

use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Support\Arr;

final class FakeStripeClient implements StripeClient
{
    public function captureAuthority(PaymentAuthorityRequest $request): PaymentAuthorityToken
    {
        if ($this->shouldFail($request)) {
            throw new PaymentGatewayException('Stripe fixture authority capture failed.');
        }

        $hash = substr(hash('sha256', json_encode($request->payload, JSON_THROW_ON_ERROR)), 0, 16);

        return new PaymentAuthorityToken(
            token: 'tok_stripe_'.$hash,
            customerRef: 'cus_stripe_'.substr($request->clientId, 0, 8),
            metadata: [
                'gateway' => 'stripe',
                'fixture' => true,
                'type' => $request->type,
            ],
        );
    }

    public function charge(PaymentChargeRequest $request): PaymentChargeResult
    {
        if ((bool) Arr::get($request->metadata, 'fixture_fail', false) || (bool) Arr::get($request->metadata, 'fixture_fail_stripe', false)) {
            throw new PaymentGatewayException('Stripe fixture charge failed.');
        }

        $hash = substr(hash('sha256', implode('|', [
            $request->authorityId,
            $request->amount,
            $request->currency,
            $request->idempotencyKey,
        ])), 0, 16);

        return new PaymentChargeResult(
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
    }

    private function shouldFail(PaymentAuthorityRequest $request): bool
    {
        return (bool) Arr::get($request->payload, 'fixture_fail', false)
            || Arr::get($request->payload, 'fixture_token') === 'fail';
    }
}
