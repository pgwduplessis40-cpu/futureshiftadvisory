<?php

declare(strict_types=1);

namespace App\Services\Integration\Cin7;

use App\Models\NzToolConnection;
use App\Services\Integration\BusinessTools\NzBusinessToolLiveClient;
use App\Services\Integration\Cin7\Contracts\Cin7Client;
use App\Services\Integration\Resilience\ResilientHttp;

final class LiveCin7Client extends NzBusinessToolLiveClient implements Cin7Client
{
    public function __construct(ResilientHttp $http, FakeCin7Client $fake)
    {
        parent::__construct($http, $fake);
    }

    protected function provider(): string
    {
        return NzToolConnection::PROVIDER_CIN7;
    }
}
