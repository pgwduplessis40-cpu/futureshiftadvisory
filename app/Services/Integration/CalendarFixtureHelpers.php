<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\CalendarConnection;
use App\Models\CalendarEventMapping;
use App\Models\Meeting;

trait CalendarFixtureHelpers
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function authorizeUrl(string $state, string $redirectUri, array $scopes): string
    {
        return 'https://'.$this->providerName().'.calendar.example.test/oauth?'.http_build_query([
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        return $this->fixtureToken('stub');
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public function pushEvent(
        CalendarConnection $connection,
        Meeting $meeting,
        array $token,
        ?CalendarEventMapping $mapping = null,
    ): array {
        return $this->fixturePushedEvent($meeting, 'stub');
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public function pullEvents(CalendarConnection $connection, array $token): array
    {
        return $this->fixturePulledEvents('stub', $connection->sync_token);
    }

    /**
     * @param  array<string, mixed>  $token
     */
    public function revoke(CalendarConnection $connection, array $token): void
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackToken(): array
    {
        return $this->fixtureToken('stub_live_fallback', degraded: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackPushedEvent(Meeting $meeting): array
    {
        return $this->fixturePushedEvent($meeting, 'stub_live_fallback', degraded: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackPulledEvents(?string $syncToken): array
    {
        return $this->fixturePulledEvents('stub_live_fallback', $syncToken, degraded: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtureToken(string $badge, bool $degraded = false): array
    {
        $provider = $this->providerName();

        return [
            'access_token' => "{$provider}-access-token-fixture",
            'refresh_token' => "{$provider}-refresh-token-fixture",
            'expires_in' => 3600,
            'external_account_id' => "{$provider}-fixture-account",
            'external_account_email' => "{$provider}.advisor@example.test",
            'sync_token' => "{$provider}-sync-1",
            'source_badge' => $badge,
            'degraded' => $degraded,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fixturePushedEvent(Meeting $meeting, string $badge, bool $degraded = false): array
    {
        $provider = $this->providerName();
        $startsAt = $meeting->scheduled_at ?? now();

        return [
            'external_event_id' => "{$provider}:meeting:{$meeting->getKey()}",
            'etag' => hash('sha1', "{$provider}|{$meeting->getKey()}|{$startsAt->toIso8601String()}"),
            'updated_at' => now()->toIso8601String(),
            'title' => $meeting->title,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $startsAt->copy()->addHour()->toIso8601String(),
            'location' => $meeting->location,
            'attendees' => $meeting->attendees ?? [],
            'source_badge' => $badge,
            'degraded' => $degraded,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fixturePulledEvents(string $badge, ?string $syncToken, bool $degraded = false): array
    {
        $provider = $this->providerName();
        $startsAt = now()->addDays(3)->setMinute(0)->setSecond(0)->setMicrosecond(0);

        return [
            'events' => [
                [
                    'external_event_id' => "{$provider}:external:advisory-roundtable",
                    'etag' => hash('sha1', "{$provider}|external|advisory-roundtable"),
                    'updated_at' => now()->toIso8601String(),
                    'title' => 'External advisory roundtable',
                    'starts_at' => $startsAt->toIso8601String(),
                    'ends_at' => $startsAt->copy()->addHour()->toIso8601String(),
                    'location' => 'Online',
                    'attendees' => ['owner@example.test'],
                ],
            ],
            'sync_token' => $syncToken === null ? "{$provider}-sync-2" : "{$syncToken}-next",
            'delta_link' => "{$provider}-delta-link-fixture",
            'source_badge' => $badge,
            'degraded' => $degraded,
        ];
    }

    abstract protected function providerName(): string;
}
