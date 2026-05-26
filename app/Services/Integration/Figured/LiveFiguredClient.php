<?php

declare(strict_types=1);

namespace App\Services\Integration\Figured;

use App\Models\AccountingConnection;
use App\Services\Integration\AccountingLiveClient;
use App\Services\Integration\Figured\Contracts\FiguredClient;
use App\Services\Integration\Resilience\ResilientHttp;

final class LiveFiguredClient extends AccountingLiveClient implements FiguredClient
{
    public function __construct(ResilientHttp $http, FakeFiguredClient $fake)
    {
        parent::__construct($http, $fake);
    }

    protected function provider(): string
    {
        return AccountingConnection::PROVIDER_FIGURED;
    }
}
