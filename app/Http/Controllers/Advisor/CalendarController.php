<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\CalendarConnection;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\PreMeetingBrief;
use App\Models\User;
use App\Services\Calendar\PublicHolidayCalendar;
use App\Services\Meetings\MeetingManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CalendarController extends Controller
{
    public function index(Request $request, PublicHolidayCalendar $publicHolidays): Response
    {
        Gate::authorize('viewAny', Client::class);

        $user = $this->advisorUser($request);
        $clientIds = $this->clientIdsFor($user);
        $rangeStart = now()->subDays(14);
        $rangeEnd = now()->addDays(120);
        $clients = $this->clientQuery($clientIds)
            ->orderBy('legal_name')
            ->limit(250)
            ->get();
        $holidayRegions = $clients
            ->flatMap(fn (Client $client): array => $publicHolidays->regionsForClient($client))
            ->unique()
            ->values()
            ->all();
        $connections = CalendarConnection::query()
            ->forUser($user)
            ->active()
            ->latest()
            ->get();

        return Inertia::render('advisor/calendar/Index', [
            'clients' => $clients
                ->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'name' => $client->legal_name,
                    'engagement_type' => $client->engagement_type,
                ])
                ->values()
                ->all(),
            'meetings' => $this->meetingQuery($clientIds)
                ->with(['client', 'preMeetingBrief'])
                ->withCount('calendarEventMappings')
                ->where('scheduled_at', '>=', $rangeStart)
                ->where('scheduled_at', '<=', $rangeEnd)
                ->orderBy('scheduled_at')
                ->limit(250)
                ->get()
                ->map(fn (Meeting $meeting): array => $this->meetingPayload($meeting))
                ->values()
                ->all(),
            'publicHolidays' => $publicHolidays->eventsBetween($rangeStart, $rangeEnd, $holidayRegions),
            'providers' => collect(CalendarConnection::providerLabels())
                ->map(fn (string $label, string $provider): array => [
                    'provider' => $provider,
                    'label' => $label,
                    'connected' => $connections->contains(
                        fn (CalendarConnection $connection): bool => $connection->provider === $provider
                            && $connection->connected(),
                    ),
                    'connect_url' => route('calendar.connect', $provider, absolute: false),
                ])
                ->values()
                ->all(),
            'storeUrl' => route('advisor.calendar.meetings.store', absolute: false),
        ]);
    }

    public function store(Request $request, MeetingManager $meetings): RedirectResponse
    {
        Gate::authorize('create', Client::class);

        $user = $this->advisorUser($request);
        $validated = $this->validated($request);
        $client = $this->clientQuery($this->clientIdsFor($user))
            ->whereKey($validated['client_id'])
            ->firstOrFail();

        $meetings->create($client, $user, $validated);

        return to_route('advisor.calendar.index')->with('status', 'meeting-created');
    }

    public function update(Request $request, Meeting $meeting, MeetingManager $meetings): RedirectResponse
    {
        $this->authorizeMeeting($request, $meeting);
        $validated = $this->validated($request, requireClient: false);

        if (isset($validated['client_id'])) {
            $this->clientQuery($this->clientIdsFor($this->advisorUser($request)))
                ->whereKey($validated['client_id'])
                ->firstOrFail();
        }

        $meetings->update($meeting, $this->advisorUser($request), $validated);

        return to_route('advisor.calendar.index')->with('status', 'meeting-updated');
    }

    public function cancel(Request $request, Meeting $meeting, MeetingManager $meetings): RedirectResponse
    {
        $this->authorizeMeeting($request, $meeting);

        $meetings->cancel($meeting, $this->advisorUser($request));

        return to_route('advisor.calendar.index')->with('status', 'meeting-cancelled');
    }

    /**
     * @return array<string, mixed>
     */
    private function meetingPayload(Meeting $meeting): array
    {
        $brief = $meeting->preMeetingBrief;
        $client = $meeting->client;

        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'status' => $meeting->status ?? Meeting::STATUS_SCHEDULED,
            'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
            'location' => $meeting->location,
            'link' => $meeting->link,
            'attendees' => $meeting->attendees ?? [],
            'calendar_synced' => $meeting->calendar_event_mappings_count > 0,
            'reminder_sent_at' => $meeting->reminder_sent_at?->toIso8601String(),
            'client' => [
                'id' => $client?->id,
                'name' => $client?->legal_name,
                'url' => $client ? route('advisor.clients.show', $client, absolute: false).'#section-meetings' : null,
            ],
            'brief' => $brief instanceof PreMeetingBrief ? [
                'id' => $brief->id,
                'status' => $brief->sent_at !== null ? 'sent' : ($brief->reviewed_at !== null ? 'reviewed' : 'draft'),
                'red_flag_count' => is_array($brief->red_flag_ids) ? count($brief->red_flag_ids) : 0,
                'url' => $client ? route('advisor.clients.show', $client, absolute: false).'#section-meetings' : null,
            ] : null,
            'update_url' => route('advisor.calendar.meetings.update', $meeting, absolute: false),
            'cancel_url' => route('advisor.calendar.meetings.cancel', $meeting, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requireClient = true): array
    {
        return $request->validate([
            'client_id' => [$requireClient ? 'required' : 'sometimes', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'link' => ['nullable', 'url', 'max:255'],
            'attendees' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function authorizeMeeting(Request $request, Meeting $meeting): void
    {
        $meeting->loadMissing('client');
        abort_unless($meeting->client instanceof Client, 404);
        Gate::authorize('update', $meeting->client);

        $clientIds = $this->clientIdsFor($this->advisorUser($request));
        abort_if(is_array($clientIds) && ! in_array((string) $meeting->client_id, $clientIds, true), 404);
    }

    private function advisorUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @return array<int, string>|null
     */
    private function clientIdsFor(User $user): ?array
    {
        if (in_array($user->user_type, [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR], true)) {
            return $user->accessibleClientIds();
        }

        return null;
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return Builder<Client>
     */
    private function clientQuery(?array $clientIds): Builder
    {
        $query = Client::query();

        if (is_array($clientIds)) {
            $clientIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('id', $clientIds);
        }

        return $query;
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return Builder<Meeting>
     */
    private function meetingQuery(?array $clientIds): Builder
    {
        $query = Meeting::query();

        if (is_array($clientIds)) {
            $clientIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('client_id', $clientIds);
        }

        return $query;
    }
}
