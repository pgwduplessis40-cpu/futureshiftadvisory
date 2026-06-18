<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Models\CalendarConnection;
use App\Services\Calendar\CalendarConnector;
use Tests\TestCase;

final class CalendarConnectorScopeTest extends TestCase
{
    public function test_configured_scopes_accept_newlines_spaces_and_commas(): void
    {
        config([
            'integrations.calendar.microsoft.scopes' => "Calendars.ReadWrite\noffline_access,User.Read",
        ]);

        $this->assertSame(
            ['Calendars.ReadWrite', 'offline_access', 'User.Read'],
            app(CalendarConnector::class)->scopes(CalendarConnection::PROVIDER_MICROSOFT),
        );
    }
}
