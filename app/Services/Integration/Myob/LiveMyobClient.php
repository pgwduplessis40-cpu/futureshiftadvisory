<?php

declare(strict_types=1);

namespace App\Services\Integration\Myob;

use App\Models\AccountingConnection;
use App\Services\Integration\AccountingLiveClient;
use App\Services\Integration\Myob\Contracts\MyobClient;
use App\Services\Integration\Resilience\ResilientHttp;

final class LiveMyobClient extends AccountingLiveClient implements MyobClient
{
    public function __construct(ResilientHttp $http, FakeMyobClient $fake)
    {
        parent::__construct($http, $fake);
    }

    protected function provider(): string
    {
        return AccountingConnection::PROVIDER_MYOB;
    }
}
