<?php

declare(strict_types=1);

namespace App\Services\Integration\Tradify;

use App\Models\NzToolConnection;
use App\Services\Integration\BusinessTools\NzBusinessToolLiveClient;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Tradify\Contracts\TradifyClient;

final class LiveTradifyClient extends NzBusinessToolLiveClient implements TradifyClient
{
    public function __construct(ResilientHttp $http, FakeTradifyClient $fake)
    {
        parent::__construct($http, $fake);
    }

    protected function provider(): string
    {
        return NzToolConnection::PROVIDER_TRADIFY;
    }
}
