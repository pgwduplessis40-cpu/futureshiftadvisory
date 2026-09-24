<?php

declare(strict_types=1);

namespace App\Services\Integration\Stripe;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Payments\IdeaValidationPaymentIntent;
use App\Services\Payments\IdeaValidationPaymentIntentRequest;
use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;
use App\Services\Payments\PaymentChargeLookup;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentRefundLookup;
use App\Services\Payments\PaymentRefundRequest;
use App\Services\Payments\PaymentRefundResult;
use App\Services\Payments\PaymentRefundSearch;
use App\Services\Payments\PaymentSetupIntent;

final class FallbackStripeClient implements StripeClient
{
    public function __construct(
        private readonly LiveStripeClient $live,
        private readonly FakeStripeClient $fake,
    ) {}

    public function createIdeaValidationPaymentIntent(IdeaValidationPaymentIntentRequest $request): IdeaValidationPaymentIntent
    {
        if ($this->usesFixtures()) {
            return $this->fake->createIdeaValidationPaymentIntent($request);
        }

        // A public purchase must never fall back to a simulated payment in a
        // live environment. If Stripe is unavailable, checkout must fail
        // clearly instead of showing a test-only completion path.
        return $this->live->createIdeaValidationPaymentIntent($request);
    }

    public function createSetupIntent(PaymentAuthorityRequest $request): PaymentSetupIntent
    {
        if ($this->usesFixtures()) {
            return $this->fake->createSetupIntent($request);
        }

        try {
            return $this->live->createSetupIntent($request);
        } catch (IntegrationDisabledException) {
            return $this->fake->createSetupIntent($request);
        }
    }

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

    public function refund(PaymentRefundRequest $request): PaymentRefundResult
    {
        try {
            return $this->live->refund($request);
        } catch (IntegrationDisabledException $exception) {
            throw new PaymentGatewayException(
                'Stripe refunds are unavailable because the live Stripe integration is not active. No refund has been issued.',
                previous: $exception,
            );
        }
    }

    public function findRefund(string $refundReference): PaymentRefundLookup
    {
        try {
            return $this->live->findRefund($refundReference);
        } catch (IntegrationDisabledException $exception) {
            throw new PaymentGatewayException(
                'Stripe refund verification is unavailable because the live Stripe integration is not active.',
                previous: $exception,
            );
        }
    }

    public function findRefundsForPayment(string $paymentReference): PaymentRefundSearch
    {
        try {
            return $this->live->findRefundsForPayment($paymentReference);
        } catch (IntegrationDisabledException $exception) {
            throw new PaymentGatewayException(
                'Stripe refund verification is unavailable because the live Stripe integration is not active.',
                previous: $exception,
            );
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
