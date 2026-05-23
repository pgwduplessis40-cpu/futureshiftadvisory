<?php

declare(strict_types=1);

namespace App\Services\Integration\Windcave\Contracts;

use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentChargeResult;

interface WindcaveClient
{
    public function captureAuthority(PaymentAuthorityRequest $request): PaymentAuthorityToken;

    public function charge(PaymentChargeRequest $request): PaymentChargeResult;
}
