<?php

declare(strict_types=1);

namespace App\Services\Integration\Windcave;

use App\Services\Integration\Windcave\Contracts\WindcaveClient;
use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentChargeLookup;
use App\Services\Payments\DefinitivePaymentDecline;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Support\Arr;

final class FakeWindcaveClient implements WindcaveClient
{
    /** @var array<string, PaymentChargeResult> */
    private array $charges = [];
    public function captureAuthority(PaymentAuthorityRequest $request): PaymentAuthorityToken
    {
        if ($this->shouldFail($request)) {
            throw new PaymentGatewayException('Windcave fixture authority capture failed.');
        }

        $hash = substr(hash('sha256', json_encode($request->payload, JSON_THROW_ON_ERROR)), 0, 16);

        return new PaymentAuthorityToken(
            token: 'tok_windcave_'.$hash,
            customerRef: 'cust_windcave_'.substr($request->clientId, 0, 8),
            metadata: [
                'gateway' => 'windcave',
                'fixture' => true,
                'type' => $request->type,
            ],
        );
    }

    public function charge(PaymentChargeRequest $request): PaymentChargeResult
    {
        if ((bool) Arr::get($request->metadata, 'fixture_fail', false) || (bool) Arr::get($request->metadata, 'fixture_fail_windcave', false)) {
            throw new DefinitivePaymentDecline('Windcave fixture charge failed.');
        }

        $hash = substr(hash('sha256', implode('|', [
            $request->authorityId,
            $request->amount,
            $request->currency,
            $request->idempotencyKey,
        ])), 0, 16);

        $result = new PaymentChargeResult(
            gateway: 'windcave',
            gatewayRef: 'txn_windcave_'.$hash,
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
