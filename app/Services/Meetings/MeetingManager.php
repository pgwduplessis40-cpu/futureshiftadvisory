<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Calendar\CalendarSync;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MeetingManager
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly CalendarSync $calendarSync,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Client $client, User $actor, array $payload): Meeting
    {
        $meeting = DB::transaction(function () use ($actor, $client, $payload): Meeting {
            /** @var Meeting $meeting */
            $meeting = Meeting::query()->create([
                'client_id' => $client->getKey(),
                'title' => (string) $payload['title'],
                'scheduled_at' => $payload['scheduled_at'],
                'location' => $this->nullableString($payload['location'] ?? null),
                'link' => $this->nullableString($payload['link'] ?? null),
                'attendees' => $this->attendees((string) ($payload['attendees'] ?? '')),
                'status' => Meeting::STATUS_SCHEDULED,
                'created_by_user_id' => $actor->getKey(),
            ]);

            $this->audit->record('meeting.created', subject: $meeting, actor: $actor, after: [
                'client_id' => $client->getKey(),
                'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
                'source' => 'advisor_calendar',
            ]);

            return $meeting;
        });

        $this->calendarSync->syncMeeting($meeting, $actor);

        return $meeting->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Meeting $meeting, User $actor, array $payload): Meeting
    {
        if (! $meeting->scheduled()) {
            throw new HttpException(422, 'Cancelled meetings cannot be edited.');
        }

        $before = $meeting->only(['title', 'scheduled_at', 'location', 'link', 'attendees']);

        DB::transaction(function () use ($actor, $before, $meeting, $payload): void {
            $meeting->forceFill([
                'client_id' => $payload['client_id'] ?? $meeting->client_id,
                'title' => (string) $payload['title'],
                'scheduled_at' => $payload['scheduled_at'],
                'location' => $this->nullableString($payload['location'] ?? null),
                'link' => $this->nullableString($payload['link'] ?? null),
                'attendees' => $this->attendees((string) ($payload['attendees'] ?? '')),
                'reminder_sent_at' => null,
            ])->save();

            $this->audit->record('meeting.updated', subject: $meeting, actor: $actor, before: $before, after: [
                'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
                'source' => 'advisor_calendar',
            ]);
        });

        $this->calendarSync->syncMeeting($meeting, $actor);

        return $meeting->refresh();
    }

    public function cancel(Meeting $meeting, User $actor): Meeting
    {
        if (! $meeting->scheduled()) {
            return $meeting;
        }

        DB::transaction(function () use ($actor, $meeting): void {
            $meeting->forceFill([
                'status' => Meeting::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->record('meeting.cancelled', subject: $meeting, actor: $actor, after: [
                'client_id' => $meeting->client_id,
                'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
            ]);
        });

        return $meeting->refresh();
    }

    /**
     * @return array<int, string>
     */
    private function attendees(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $attendee): string => trim($attendee))
            ->filter()
            ->values()
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
