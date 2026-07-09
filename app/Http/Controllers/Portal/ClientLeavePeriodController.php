<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClientLeavePeriod;
use App\Models\User;
use App\Services\Calendar\ClientAvailabilityCalendar;
use App\Services\Portal\ClientPortalResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ClientLeavePeriodController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly ClientAvailabilityCalendar $availability,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $leave = ClientLeavePeriod::query()->create([
            'client_id' => $client->getKey(),
            'created_by_user_id' => $user->getKey(),
            'title' => $this->title($validated['title'] ?? null),
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'],
            'notes' => $this->nullableString($validated['notes'] ?? null),
        ]);

        $rescheduled = $this->availability->rescheduleOpenDueDates($client, $leave);

        return to_route('portal.calendar.index')
            ->with('status', 'leave-period-created')
            ->with('leave_rescheduled_count', array_sum($rescheduled));
    }

    public function destroy(Request $request, ClientLeavePeriod $leavePeriod): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        abort_unless((string) $leavePeriod->client_id === (string) $client->getKey(), 404);

        $leavePeriod->delete();

        return to_route('portal.calendar.index')->with('status', 'leave-period-deleted');
    }

    private function title(mixed $value): string
    {
        $title = trim((string) ($value ?? ''));

        return $title === '' ? 'Leave' : $title;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
