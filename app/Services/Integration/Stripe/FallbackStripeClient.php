<?php

declare(strict_types=1);

namespace App\Services\Integration\Stripe;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentSetupIntent;

final class FallbackStripeClient implements StripeClient
{
    public function __construct(
        private readonly LiveStripeClient $live,
        private readonly FakeStripeClient $fake,
    ) {}

    public function createSetupIntent(PaymentAuthorityRequest $request): PaymentSetupIntent
    {
        try {
            return $this->live->createSetupIntent($request);
        } catch (IntegrationDisabledException) {
            return $this->fake->createSetupIntent($request);
        }
    }

    public function captureAuthority(PaymentAuthorityRequest $request): PaymentAuthorityToken
    {
        try {
            return $this->live->captureAuthority($request);
        } catch (IntegrationDisabledException) {
            return $this->fake->captureAuthority($request);
        }
    }

    public function charge(PaymentChargeRequest $request): PaymentChargeResult
    {
        try {
            return $this->live->charge($request);
        } catch (IntegrationDisabledException) {
            return $this->fake->charge($request);
        }
    }
}
