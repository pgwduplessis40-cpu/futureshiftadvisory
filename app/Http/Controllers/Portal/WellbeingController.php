<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Models\WellbeingCheckin;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Wellbeing\WellbeingCheckinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class WellbeingController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly WellbeingCheckinService $checkins,
    ) {}

    public function show(Request $request): Response
    {
        $client = $this->clients->resolveFor($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return Inertia::render('portal/wellbeing/Pulse', $this->payload($client, $user));
    }

    public function store(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'business_confidence' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'personal_coping' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->checkins->record($client, $user, [
            'business_confidence' => (int) $validated['business_confidence'],
            'personal_coping' => (int) $validated['personal_coping'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return to_route('portal.wellbeing.show')->with('status', 'wellbeing-saved');
    }

    public function destroy(Request $request, WellbeingCheckin $wellbeingCheckin): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless((string) $wellbeingCheckin->client_id === (string) $client->getKey(), 404);

        $this->checkins->delete($wellbeingCheckin, $user);

        return to_route('portal.wellbeing.show')->with('status', 'wellbeing-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Client $client, User $user): array
    {
        $current = $this->currentCheckin($client, $user);

        return [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
                'trading_name' => $client->trading_name,
            ],
            'periodStart' => now()->startOfMonth()->toDateString(),
            'currentCheckin' => $current instanceof WellbeingCheckin ? $this->checkinPayload($current, $user) : null,
            'storeUrl' => route('portal.wellbeing.store'),
            'dashboardUrl' => route('portal.dashboard'),
        ];
    }

    private function currentCheckin(Client $client, User $user): ?WellbeingCheckin
    {
        return WellbeingCheckin::query()
            ->where('client_id', $client->getKey())
            ->where('user_id', $user->getKey())
            ->whereDate('period_start', now()->startOfMonth()->toDateString())
            ->latest('submitted_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function checkinPayload(WellbeingCheckin $checkin, User $user): array
    {
        return [
            'id' => $checkin->id,
            'business_confidence' => $checkin->business_confidence,
            'personal_coping' => $checkin->personal_coping,
            'notes' => $checkin->notes,
            'submitted_at' => $checkin->submitted_at?->toIso8601String(),
            'can_delete' => $checkin->canBeDeletedBy($user),
            'delete_url' => route('portal.wellbeing.destroy', $checkin),
        ];
    }
}
