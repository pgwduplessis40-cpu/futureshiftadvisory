<?php

declare(strict_types=1);

namespace App\Services\Integration\QuickBooks;

use App\Models\AccountingConnection;
use App\Services\Integration\AccountingLiveClient;
use App\Services\Integration\QuickBooks\Contracts\QuickBooksClient;
use App\Services\Integration\Resilience\ResilientHttp;

final class LiveQuickBooksClient extends AccountingLiveClient implements QuickBooksClient
{
    public function __construct(ResilientHttp $http, FakeQuickBooksClient $fake)
    {
        parent::__construct($http, $fake);
    }

    protected function provider(): string
    {
        return AccountingConnection::PROVIDER_QUICKBOOKS;
    }
}
