<?php

declare(strict_types=1);

namespace App\Services\Integration\MicrosoftGraph;

use App\Models\CalendarConnection;
use App\Models\CalendarEventMapping;
use App\Models\Meeting;
use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\MicrosoftGraph\Contracts\MicrosoftGraphClient;

final class FallbackMicrosoftGraphClient implements MicrosoftGraphClient
{
    public function __construct(
        private readonly LiveMicrosoftGraphClient $live,
        private readonly FakeMicrosoftGraphClient $fake,
    ) {}

    public function authorizeUrl(string $state, string $redirectUri, array $scopes): string
    {
        return $this->live->authorizeUrl($state, $redirectUri, $scopes);
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        try {
            return $this->live->exchangeCodeForToken($code, $redirectUri);
        } catch (IntegrationDisabledException) {
            return $this->fake->exchangeCodeForToken($code, $redirectUri);
        }
    }

    public function pushEvent(
        CalendarConnection $connection,
        Meeting $meeting,
        array $token,
        ?CalendarEventMapping $mapping = null,
    ): array {
        try {
            return $this->live->pushEvent($connection, $meeting, $token, $mapping);
        } catch (IntegrationDisabledException) {
            return $this->fake->pushEvent($connection, $meeting, $token, $mapping);
        }
    }

    public function pullEvents(CalendarConnection $connection, array $token): array
    {
        try {
            return $this->live->pullEvents($connection, $token);
        } catch (IntegrationDisabledException) {
            return $this->fake->pullEvents($connection, $token);
        }
    }

    public function revoke(CalendarConnection $connection, array $token): void
    {
        try {
            $this->live->revoke($connection, $token);
        } catch (IntegrationDisabledException) {
            $this->fake->revoke($connection, $token);
        }
    }
}
