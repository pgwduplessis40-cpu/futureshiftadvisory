<?php

declare(strict_types=1);

namespace App\Services\Integration\Windcave;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Windcave\Contracts\WindcaveClient;
use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentChargeLookup;

final class FallbackWindcaveClient implements WindcaveClient
{
    public function __construct(
        private readonly LiveWindcaveClient $live,
        private readonly FakeWindcaveClient $fake,
    ) {}

    public function captureAuthority(PaymentAuthorityRequest $request): PaymentAuthorityToken
    {
        if ($this->usesFixtures()) {
            return $this->fake->captureAuthority($request);
        }

        try {
            return $this->live->captureAuthority($request);
        } catch (IntegrationDisabledException) {
            return $this->fake->captureAuthority($request);
        }
    }

    public function charge(PaymentChargeRequest $request): PaymentChargeResult
    {
        if ($this->usesFixtures()) {
            return $this->fake->charge($request);
        }

        try {
            return $this->live->charge($request);
        } catch (IntegrationDisabledException) {
            return $this->fake->charge($request);
        }
    }

    public function findCharge(?string $gatewayRef, string $idempotencyKey, string $paymentId): PaymentChargeLookup
    {
        if ($this->usesFixtures()) {
            return $this->fake->findCharge($gatewayRef, $idempotencyKey, $paymentId);
        }

        try {
            return $this->live->findCharge($gatewayRef, $idempotencyKey, $paymentId);
        } catch (IntegrationDisabledException) {
            return $this->fake->findCharge($gatewayRef, $idempotencyKey, $paymentId);
        }
    }

    private function usesFixtures(): bool
    {
        return app()->environment(['local', 'testing']);
    }
}
