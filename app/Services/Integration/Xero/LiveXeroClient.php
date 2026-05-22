<?php

declare(strict_types=1);

namespace App\Services\Integration\Xero;

use App\Models\AccountingConnection;
use App\Services\Integration\AccountingLiveClient;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Xero\Contracts\XeroClient;

final class LiveXeroClient extends AccountingLiveClient implements XeroClient
{
    public function __construct(ResilientHttp $http, FakeXeroClient $fake)
    {
        parent::__construct($http, $fake);
    }

    protected function provider(): string
    {
        return AccountingConnection::PROVIDER_XERO;
    }
}
