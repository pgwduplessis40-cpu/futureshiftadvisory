<?php

declare(strict_types=1);

namespace App\Services\Integration\MicrosoftGraph;

use App\Models\CalendarConnection;
use App\Services\Integration\CalendarFixtureHelpers;
use App\Services\Integration\MicrosoftGraph\Contracts\MicrosoftGraphClient;

final class FakeMicrosoftGraphClient implements MicrosoftGraphClient
{
    use CalendarFixtureHelpers;

    protected function providerName(): string
    {
        return CalendarConnection::PROVIDER_MICROSOFT;
    }
}
