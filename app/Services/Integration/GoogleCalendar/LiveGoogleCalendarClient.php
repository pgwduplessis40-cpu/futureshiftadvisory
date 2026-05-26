<?php

declare(strict_types=1);

namespace App\Services\Integration\GoogleCalendar;

use App\Models\CalendarConnection;
use App\Services\Integration\CalendarLiveClient;
use App\Services\Integration\GoogleCalendar\Contracts\GoogleCalendarClient;
use App\Services\Integration\Resilience\ResilientHttp;

final class LiveGoogleCalendarClient extends CalendarLiveClient implements GoogleCalendarClient
{
    public function __construct(ResilientHttp $http, FakeGoogleCalendarClient $fake)
    {
        parent::__construct($http, $fake);
    }

    protected function provider(): string
    {
        return CalendarConnection::PROVIDER_GOOGLE;
    }
}
