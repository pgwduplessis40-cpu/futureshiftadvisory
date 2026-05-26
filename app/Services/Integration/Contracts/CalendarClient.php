<?php

declare(strict_types=1);

namespace App\Services\Integration\Contracts;

use App\Models\CalendarConnection;
use App\Models\CalendarEventMapping;
use App\Models\Meeting;

interface CalendarClient
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function authorizeUrl(string $state, string $redirectUri, array $scopes): string;

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array;

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public function pushEvent(
        CalendarConnection $connection,
        Meeting $meeting,
        array $token,
        ?CalendarEventMapping $mapping = null,
    ): array;

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public function pullEvents(CalendarConnection $connection, array $token): array;

    /**
     * @param  array<string, mixed>  $token
     */
    public function revoke(CalendarConnection $connection, array $token): void;
}
