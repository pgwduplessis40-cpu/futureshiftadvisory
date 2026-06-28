<?php

declare(strict_types=1);

namespace App\Services\Integration\Stripe\Contracts;

use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentSetupIntent;

interface StripeClient
{
    public function createSetupIntent(PaymentAuthorityRequest $request): PaymentSetupIntent;

    public function captureAuthority(PaymentAuthorityRequest $request): PaymentAuthorityToken;

    public function charge(PaymentChargeRequest $request): PaymentChargeResult;
}
