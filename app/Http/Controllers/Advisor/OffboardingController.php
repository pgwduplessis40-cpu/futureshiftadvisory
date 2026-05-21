<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\OffboardingRecord;
use App\Models\User;
use App\Services\Offboarding\OffboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class OffboardingController extends Controller
{
    public function create(Client $client, OffboardingService $offboarding): Response
    {
        Gate::authorize('update', $client);

        $client->loadMissing('primaryContact');
        $latest = $client->offboardingRecords()
            ->latest('triggered_at')
            ->first();

        return Inertia::render('advisor/clients/Offboard', [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
                'nzbn' => $client->nzbn,
                'primary_contact_name' => $client->primaryContact?->name,
                'primary_contact_email' => $client->primaryContact?->email,
            ],
            'latestOffboarding' => $latest instanceof OffboardingRecord
                ? $this->recordPayload($latest)
                : null,
            'reengagementDays' => $offboarding->reengagementDays(),
            'submitUrl' => route('advisor.clients.offboarding.store', $client, absolute: false),
            'backUrl' => route('advisor.clients.show', $client, absolute: false),
        ]);
    }

    public function store(Request $request, Client $client, OffboardingService $offboarding): RedirectResponse
    {
        Gate::authorize('update', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'exit_interview_notes' => ['nullable', 'string', 'max:2000'],
            'handover_notes' => ['nullable', 'string', 'max:2000'],
            'privacy_acknowledged' => ['accepted'],
        ]);

        $offboarding->trigger($client, $user, [
            'exit_interview_notes' => $validated['exit_interview_notes'] ?? null,
            'handover_notes' => $validated['handover_notes'] ?? null,
            'privacy_acknowledged' => true,
        ]);

        return to_route('advisor.clients.show', $client)->with('status', 'offboarding-triggered');
    }

    /**
     * @return array<string, mixed>
     */
    private function recordPayload(OffboardingRecord $record): array
    {
        return [
            'id' => $record->id,
            'triggered_at' => $record->triggered_at?->toIso8601String(),
            'reengagement_due' => $record->reengagement_due?->toIso8601String(),
            'advisor_capacity_released' => $record->advisor_capacity_released,
        ];
    }
}
