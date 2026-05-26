<?php

declare(strict_types=1);

namespace App\Services\Integration\MicrosoftGraph;

use App\Models\CalendarConnection;
use App\Services\Integration\CalendarLiveClient;
use App\Services\Integration\MicrosoftGraph\Contracts\MicrosoftGraphClient;
use App\Services\Integration\Resilience\ResilientHttp;

final class LiveMicrosoftGraphClient extends CalendarLiveClient implements MicrosoftGraphClient
{
    public function __construct(ResilientHttp $http, FakeMicrosoftGraphClient $fake)
    {
        parent::__construct($http, $fake);
    }

    protected function provider(): string
    {
        return CalendarConnection::PROVIDER_MICROSOFT;
    }
}
