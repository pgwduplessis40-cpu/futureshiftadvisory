<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\CalendarConnection;
use App\Services\Integration\Contracts\CalendarClient;
use App\Services\Integration\GoogleCalendar\Contracts\GoogleCalendarClient;
use App\Services\Integration\MicrosoftGraph\Contracts\MicrosoftGraphClient;
use InvalidArgumentException;

final class CalendarClientResolver
{
    public function __construct(
        private readonly GoogleCalendarClient $google,
        private readonly MicrosoftGraphClient $microsoft,
    ) {}

    public function client(string $provider): CalendarClient
    {
        return match ($provider) {
            CalendarConnection::PROVIDER_GOOGLE => $this->google,
            CalendarConnection::PROVIDER_MICROSOFT => $this->microsoft,
            default => throw new InvalidArgumentException("Unsupported calendar provider [{$provider}]."),
        };
    }
}
