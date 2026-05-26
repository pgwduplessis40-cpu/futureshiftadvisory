<?php

declare(strict_types=1);

namespace App\Services\Integration\GoogleCalendar;

use App\Models\CalendarConnection;
use App\Services\Integration\CalendarFixtureHelpers;
use App\Services\Integration\GoogleCalendar\Contracts\GoogleCalendarClient;

final class FakeGoogleCalendarClient implements GoogleCalendarClient
{
    use CalendarFixtureHelpers;

    protected function providerName(): string
    {
        return CalendarConnection::PROVIDER_GOOGLE;
    }
}
