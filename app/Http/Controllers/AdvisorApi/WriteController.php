<?php

declare(strict_types=1);

namespace App\Http\Controllers\AdvisorApi;

use App\Http\Controllers\Controller;
use App\Models\AdvisorApiClient;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\Milestone;
use App\Models\MilestoneAction;
use App\Services\Calendar\PublicHolidayCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class WriteController extends Controller
{
    public function meetingNote(Request $request, Client $client, PublicHolidayCalendar $publicHolidays): JsonResponse
    {
        $apiClient = $this->apiClient($request);
        $this->authorizeScope($apiClient, AdvisorApiClient::SCOPE_WRITE_MEETING_NOTES);
        $this->authorizeClient($apiClient, $client);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'note' => ['required', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date'],
        ]);
        $this->assertDateAllowed(
            $client,
            $validated['occurred_at'] ?? now(),
            'occurred_at',
            'Meetings',
            $publicHolidays,
        );

        /** @var Meeting $meeting */
        $meeting = Meeting::query()->create([
            'client_id' => $client->id,
            'title' => $validated['title'],
            'scheduled_at' => $validated['occurred_at'] ?? now(),
            'attendees' => [
                'source' => 'advisor_api',
                'note' => $validated['note'],
                'advisor_api_client_id' => $apiClient->id,
            ],
            'created_by_user_id' => $apiClient->advisor_user_id,
            'external_ref' => 'advisor-api:'.now()->timestamp,
        ]);

        return response()->json(['data' => ['id' => $meeting->id]], 201);
    }

    public function action(Request $request, Client $client, PublicHolidayCalendar $publicHolidays): JsonResponse
    {
        $apiClient = $this->apiClient($request);
        $this->authorizeScope($apiClient, AdvisorApiClient::SCOPE_WRITE_ACTIONS);
        $this->authorizeClient($apiClient, $client);

        $validated = $request->validate([
            'milestone_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:180'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'max:40'],
        ]);
        $milestone = Milestone::query()
            ->where('client_id', $client->id)
            ->whereKey($validated['milestone_id'])
            ->firstOrFail();
        $this->assertDateAllowed(
            $client,
            $validated['due_date'] ?? null,
            'due_date',
            'Actions',
            $publicHolidays,
        );

        /** @var MilestoneAction $action */
        $action = MilestoneAction::query()->create([
            'milestone_id' => $milestone->id,
            'client_id' => $client->id,
            'title' => $validated['title'],
            'owner_user_id' => $apiClient->advisor_user_id,
            'due_date' => $validated['due_date'] ?? null,
            'priority' => $validated['priority'] ?? 'normal',
            'status' => MilestoneAction::STATUS_PENDING,
        ]);

        return response()->json(['data' => ['id' => $action->id]], 201);
    }

    private function apiClient(Request $request): AdvisorApiClient
    {
        $client = $request->attributes->get('advisor_api_client');

        abort_unless($client instanceof AdvisorApiClient, 401);

        return $client;
    }

    private function authorizeScope(AdvisorApiClient $apiClient, string $scope): void
    {
        abort_unless($apiClient->allows($scope), 403, 'Advisor API scope is not allowed.');
    }

    private function authorizeClient(AdvisorApiClient $apiClient, Client $client): void
    {
        abort_unless(in_array((string) $client->id, $apiClient->advisor->accessibleClientIds(), true), 404);
    }

    private function assertDateAllowed(
        Client $client,
        mixed $date,
        string $field,
        string $subject,
        PublicHolidayCalendar $publicHolidays,
    ): void {
        if ($date === null || trim((string) $date) === '') {
            return;
        }

        $holiday = $publicHolidays->holidayOn($date, $publicHolidays->regionsForClient($client));

        if ($holiday !== null) {
            throw ValidationException::withMessages([
                $field => $publicHolidays->validationMessage($holiday, $subject),
            ]);
        }
    }
}
