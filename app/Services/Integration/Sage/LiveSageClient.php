<?php

declare(strict_types=1);

namespace App\Services\Integration\Sage;

use App\Models\AccountingConnection;
use App\Services\Integration\AccountingLiveClient;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Sage\Contracts\SageClient;

final class LiveSageClient extends AccountingLiveClient implements SageClient
{
    public function __construct(ResilientHttp $http, FakeSageClient $fake)
    {
        parent::__construct($http, $fake);
    }

    protected function provider(): string
    {
        return AccountingConnection::PROVIDER_SAGE;
    }
}
