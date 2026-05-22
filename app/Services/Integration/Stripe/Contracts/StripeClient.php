<?php

declare(strict_types=1);

namespace App\Services\Integration\Stripe\Contracts;

use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;

interface StripeClient
{
    public function captureAuthority(PaymentAuthorityRequest $request): PaymentAuthorityToken;
}
