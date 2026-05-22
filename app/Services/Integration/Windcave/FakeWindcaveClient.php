<?php

declare(strict_types=1);

namespace App\Services\Integration\Windcave;

use App\Services\Integration\Windcave\Contracts\WindcaveClient;
use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Support\Arr;

final class FakeWindcaveClient implements WindcaveClient
{
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

    private function shouldFail(PaymentAuthorityRequest $request): bool
    {
        return (bool) Arr::get($request->payload, 'fixture_fail', false)
            || Arr::get($request->payload, 'fixture_token') === 'fail';
    }
}
