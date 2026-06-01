<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\CalendarConnection;
use App\Models\CalendarEventMapping;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;

final class CalendarSync
{
    public function __construct(
        private readonly CalendarClientResolver $clients,
        private readonly CalendarConnector $connector,
        private readonly AuditWriter $audit,
    ) {}

    public function syncConnection(CalendarConnection $connection, ?User $actor = null): int
    {
        if (! $connection->connected()) {
            return 0;
        }

        $processed = 0;
        $actor ??= $connection->user;

        Meeting::query()
            ->where('created_by_user_id', $connection->user_id)
            ->where('status', Meeting::STATUS_SCHEDULED)
            ->where('scheduled_at', '>=', now()->subDay())
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get()
            ->each(function (Meeting $meeting) use ($actor, $connection, &$processed): void {
                $this->pushMeetingToConnection($connection, $meeting, $actor);
                $processed++;
            });

        $processed += $this->pullExternalEvents($connection, $actor);

        $connection->forceFill([
            'last_synced_at' => now(),
            'status' => CalendarConnection::STATUS_CONNECTED,
        ])->save();

        $this->audit->record('calendar_connection.synced', subject: $connection, actor: $actor, after: [
            'provider' => $connection->provider,
            'processed' => $processed,
            'sync_token' => $connection->sync_token,
            'delta_link_present' => $connection->delta_link !== null,
        ]);

        return $processed;
    }

    public function syncMeeting(Meeting $meeting, User $actor): int
    {
        $processed = 0;

        CalendarConnection::query()
            ->forUser($actor)
            ->connected()
            ->get()
            ->each(function (CalendarConnection $connection) use ($actor, $meeting, &$processed): void {
                $this->pushMeetingToConnection($connection, $meeting, $actor);
                $processed++;
            });

        return $processed;
    }

    private function pushMeetingToConnection(CalendarConnection $connection, Meeting $meeting, ?User $actor): CalendarEventMapping
    {
        $mapping = CalendarEventMapping::query()
            ->where('calendar_connection_id', $connection->getKey())
            ->where('meeting_id', $meeting->getKey())
            ->where('is_external_only', false)
            ->first();

        $event = $this->clients
            ->client($connection->provider)
            ->pushEvent($connection, $meeting, $this->connector->decryptToken($connection), $mapping);

        return DB::transaction(function () use ($actor, $connection, $event, $mapping, $meeting): CalendarEventMapping {
            $externalEventId = (string) ($event['external_event_id'] ?? "{$connection->provider}:meeting:{$meeting->getKey()}");

            $mapping = CalendarEventMapping::query()->updateOrCreate(
                [
                    'calendar_connection_id' => $connection->getKey(),
                    'external_event_id' => $externalEventId,
                ],
                [
                    'meeting_id' => $meeting->getKey(),
                    'etag' => $event['etag'] ?? null,
                    'provider_updated_at' => $event['updated_at'] ?? now(),
                    'direction' => CalendarEventMapping::DIRECTION_PUSH,
                    'origin' => CalendarEventMapping::ORIGIN_FSA,
                    'title' => $event['title'] ?? $meeting->title,
                    'starts_at' => $event['starts_at'] ?? $meeting->scheduled_at,
                    'ends_at' => $event['ends_at'] ?? $meeting->scheduled_at?->copy()->addHour(),
                    'location' => $event['location'] ?? $meeting->location,
                    'attendees' => $event['attendees'] ?? $meeting->attendees ?? [],
                    'is_external_only' => false,
                    'last_synced_at' => now(),
                ],
            );

            $this->audit->record('calendar_event_mapping.pushed', subject: $mapping, actor: $actor, after: [
                'calendar_connection_id' => $connection->getKey(),
                'meeting_id' => $meeting->getKey(),
                'external_event_id' => $externalEventId,
                'source_badge' => $event['source_badge'] ?? null,
                'degraded' => $event['degraded'] ?? false,
            ]);

            return $mapping;
        });
    }

    private function pullExternalEvents(CalendarConnection $connection, ?User $actor): int
    {
        $payload = $this->clients
            ->client($connection->provider)
            ->pullEvents($connection, $this->connector->decryptToken($connection));

        $events = is_array($payload['events'] ?? null) ? $payload['events'] : [];
        $processed = 0;

        DB::transaction(function () use ($actor, $connection, $events, $payload, &$processed): void {
            foreach ($events as $event) {
                if (! is_array($event) || ! is_scalar($event['external_event_id'] ?? null)) {
                    continue;
                }

                $existing = CalendarEventMapping::query()
                    ->where('calendar_connection_id', $connection->getKey())
                    ->where('external_event_id', (string) $event['external_event_id'])
                    ->first();

                $meetingId = $existing?->meeting_id;
                $externalOnly = $meetingId === null;

                CalendarEventMapping::query()->updateOrCreate(
                    [
                        'calendar_connection_id' => $connection->getKey(),
                        'external_event_id' => (string) $event['external_event_id'],
                    ],
                    [
                        'meeting_id' => $meetingId,
                        'etag' => $event['etag'] ?? null,
                        'provider_updated_at' => $event['updated_at'] ?? now(),
                        'direction' => $externalOnly
                            ? CalendarEventMapping::DIRECTION_PULL
                            : CalendarEventMapping::DIRECTION_TWO_WAY,
                        'origin' => $existing?->origin ?? CalendarEventMapping::ORIGIN_EXTERNAL,
                        'title' => $event['title'] ?? 'External event',
                        'starts_at' => $event['starts_at'] ?? null,
                        'ends_at' => $event['ends_at'] ?? null,
                        'location' => $event['location'] ?? null,
                        'attendees' => $event['attendees'] ?? [],
                        'is_external_only' => $externalOnly,
                        'last_synced_at' => now(),
                    ],
                );

                $processed++;
            }

            $connection->forceFill([
                'sync_token' => $payload['sync_token'] ?? $connection->sync_token,
                'delta_link' => $payload['delta_link'] ?? $connection->delta_link,
                'last_synced_at' => now(),
            ])->save();

            if ($processed > 0) {
                $this->audit->record('calendar_events.pulled', subject: $connection, actor: $actor, after: [
                    'provider' => $connection->provider,
                    'external_event_count' => $processed,
                    'source_badge' => $payload['source_badge'] ?? null,
                    'degraded' => $payload['degraded'] ?? false,
                ]);
            }
        });

        return $processed;
    }
}
