<?php

declare(strict_types=1);

namespace App\Services\Integration\Windcave\Contracts;

use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;

interface WindcaveClient
{
    public function captureAuthority(PaymentAuthorityRequest $request): PaymentAuthorityToken;
}
