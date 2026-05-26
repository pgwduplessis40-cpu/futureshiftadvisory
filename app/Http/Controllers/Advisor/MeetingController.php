<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Calendar\CalendarSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class MeetingController extends Controller
{
    public function store(Request $request, Client $client, AuditWriter $audit, CalendarSync $calendarSync): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'link' => ['nullable', 'url', 'max:255'],
            'attendees' => ['nullable', 'string', 'max:1000'],
        ]);

        $meeting = Meeting::query()->create([
            'client_id' => $client->getKey(),
            'title' => $validated['title'],
            'scheduled_at' => $validated['scheduled_at'],
            'location' => $validated['location'] ?? null,
            'link' => $validated['link'] ?? null,
            'attendees' => $this->attendees((string) ($validated['attendees'] ?? '')),
            'created_by_user_id' => $user->getKey(),
        ]);

        $audit->record('meeting.created', subject: $meeting, actor: $user, after: [
            'client_id' => $client->getKey(),
            'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
        ]);

        $calendarSync->syncMeeting($meeting, $user);

        return to_route('advisor.clients.show', $client)->with('status', 'meeting-created');
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
}
