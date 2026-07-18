<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PilotFeeWaiverProgram;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Fees\PilotFeeWaiverManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PilotFeeWaiverController extends Controller
{
    public function __construct(
        private readonly PilotFeeWaiverManager $waivers,
        private readonly AuditWriter $audit,
    ) {}

    public function index(): Response
    {
        $program = $this->waivers->currentProgram();

        return Inertia::render('admin/pilot-fee-waivers/Index', [
            'program' => [
                'status' => $program->status,
                'updated_at' => $program->updated_at?->toIso8601String(),
                'updated_by_name' => $program->updatedBy?->name,
                'update_url' => route('admin.pilot-fee-waivers.program.update', absolute: false),
            ],
            'statuses' => PilotFeeWaiverProgram::statuses(),
            'clients' => Client::query()
                ->with('pilotFeeWaiverApprovedBy:id,name')
                ->orderBy('legal_name')
                ->limit(250)
                ->get()
                ->map(fn (Client $client): array => $this->clientPayload($client))
                ->values(),
        ]);
    }

    public function updateProgram(Request $request): RedirectResponse
    {
        $actor = $this->superAdmin($request);
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(PilotFeeWaiverProgram::statuses())],
        ]);
        $before = $this->programPayload($this->waivers->currentProgram());
        $program = $this->waivers->updateProgram($validated['status'], $actor);

        $this->audit->record('pilot_fee_waiver_program.updated', subject: $program, actor: $actor, before: $before, after: $this->programPayload($program));

        return to_route('admin.pilot-fee-waivers.index')->with('status', 'pilot-fee-waiver-program-updated');
    }

    public function updateClient(Request $request, Client $client): RedirectResponse
    {
        $actor = $this->superAdmin($request);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $enabled = (bool) $validated['enabled'];

        if ($enabled && ! $this->waivers->currentProgram()->allowsNewWaivers()) {
            throw ValidationException::withMessages([
                'enabled' => 'Open the pilot fee-waiver programme before assigning a client waiver.',
            ]);
        }

        if ($enabled && blank($validated['starts_at'] ?? null)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Set the date the pilot fee waiver starts.',
            ]);
        }

        if ($enabled && blank($validated['expires_at'] ?? null)) {
            throw ValidationException::withMessages([
                'expires_at' => 'Set a review or expiry date for the pilot fee waiver.',
            ]);
        }

        $reason = isset($validated['reason']) ? trim((string) $validated['reason']) : null;
        if ($enabled && $reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Record why this client is approved for the pilot fee waiver.',
            ]);
        }

        $before = $this->clientAuditPayload($client);
        $updated = $this->waivers->updateClient($client, [
            'enabled' => $enabled,
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'reason' => $reason,
        ], $actor);

        $this->audit->record('client.pilot_fee_waiver.updated', subject: $updated, actor: $actor, before: $before, after: $this->clientAuditPayload($updated));

        return to_route('admin.pilot-fee-waivers.index')->with('status', 'pilot-fee-waiver-client-updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(Client $client): array
    {
        $eligibility = $this->waivers->eligibility($client);

        return [
            'id' => $client->id,
            'legal_name' => $client->legal_name,
            'trading_name' => $client->trading_name,
            'engagement_type' => $client->engagement_type instanceof \BackedEnum
                ? $client->engagement_type->value
                : (string) $client->engagement_type,
            'enabled' => (bool) $client->pilot_fee_waiver_enabled,
            'starts_at' => $client->pilot_fee_waiver_starts_at?->toIso8601String(),
            'expires_at' => $client->pilot_fee_waiver_expires_at?->toIso8601String(),
            'reason' => $client->pilot_fee_waiver_reason,
            'approved_by_name' => $client->pilotFeeWaiverApprovedBy?->name,
            'approved_at' => $client->pilot_fee_waiver_approved_at?->toIso8601String(),
            'active_for_new_proposals' => $eligibility['eligible'],
            'update_url' => route('admin.pilot-fee-waivers.clients.update', $client, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function programPayload(PilotFeeWaiverProgram $program): array
    {
        return [
            'status' => $program->status,
            'updated_by_user_id' => $program->updated_by_user_id,
            'updated_at' => $program->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clientAuditPayload(Client $client): array
    {
        return [
            'enabled' => (bool) $client->pilot_fee_waiver_enabled,
            'starts_at' => $client->pilot_fee_waiver_starts_at?->toIso8601String(),
            'expires_at' => $client->pilot_fee_waiver_expires_at?->toIso8601String(),
            'reason' => $client->pilot_fee_waiver_reason,
            'approved_by_user_id' => $client->pilot_fee_waiver_approved_by_user_id,
            'approved_at' => $client->pilot_fee_waiver_approved_at?->toIso8601String(),
        ];
    }

    private function superAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_SUPER_ADMIN, 403);

        return $user;
    }
}
