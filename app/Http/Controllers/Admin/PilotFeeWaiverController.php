<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\PilotFeeWaiverProgram;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Fees\PilotFeeWaiverManager;
use App\Services\ServiceActivations\PilotFeeWaiverActivationReconciler;
use App\Support\RequestContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class PilotFeeWaiverController extends Controller
{
    public function __construct(
        private readonly PilotFeeWaiverManager $waivers,
        private readonly PilotFeeWaiverActivationReconciler $activationWaivers,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $program = $this->waivers->currentProgram();
        [$clients, $entrepreneurs] = $this->context->withSystemContext(
            fn (): array => [
                Client::query()
                    ->withoutOperationalHealthFixtures()
                    ->with('pilotFeeWaiverApprovedBy:id,name')
                    ->orderBy('legal_name')
                    ->get(),
                EntrepreneurProfile::query()
                    ->withoutOperationalHealthFixtures()
                    ->with('pilotFeeWaiverApprovedBy:id,name')
                    ->orderBy('name')
                    ->get(),
            ]
        );
        $clientIds = $clients->modelKeys();
        $convertedProfileIds = $clients
            ->map(fn (Client $client): ?string => data_get($client->registry_sources, 'entrepreneur_profile_id'))
            ->filter()
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
        $clientRows = $clients->map(fn (Client $client): array => $this->clientPayload($client));
        $entrepreneurRows = $entrepreneurs
            ->reject(
                fn (EntrepreneurProfile $profile): bool => (
                    $profile->client_id !== null
                    && in_array((string) $profile->client_id, $clientIds, true)
                ) || in_array((string) $profile->getKey(), $convertedProfileIds, true)
            )
            ->map(fn (EntrepreneurProfile $profile): array => $this->entrepreneurPayload($profile));
        $subjects = $clientRows
            ->concat($entrepreneurRows)
            ->sortBy('legal_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $response = Inertia::render('admin/pilot-fee-waivers/Index', [
            'program' => [
                'status' => $program->status,
                'updated_at' => $program->updated_at?->toIso8601String(),
                'updated_by_name' => $program->updatedBy?->name,
                'update_url' => route('admin.pilot-fee-waivers.program.update', absolute: false),
            ],
            'statuses' => PilotFeeWaiverProgram::statuses(),
            'clients' => $subjects,
        ])->toResponse($request);

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
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
        $attributes = $this->validatedWaiverAttributes($request);

        $before = $this->clientAuditPayload($client);
        $updated = $this->waivers->updateClient($client, $attributes, $actor);
        $this->activationWaivers->reconcileForClient($updated, $actor);

        $this->audit->record('client.pilot_fee_waiver.updated', subject: $updated, actor: $actor, before: $before, after: $this->clientAuditPayload($updated));

        return to_route('admin.pilot-fee-waivers.index')->with('status', 'pilot-fee-waiver-client-updated');
    }

    public function updateEntrepreneur(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
    ): RedirectResponse {
        $actor = $this->superAdmin($request);
        $attributes = $this->validatedWaiverAttributes($request);
        $before = $this->clientAuditPayload($entrepreneurProfile);
        $updated = $this->waivers->updateEntrepreneur($entrepreneurProfile, $attributes, $actor);

        $this->audit->record(
            'entrepreneur.pilot_fee_waiver.updated',
            subject: $updated,
            actor: $actor,
            before: $before,
            after: $this->clientAuditPayload($updated),
        );

        return to_route('admin.pilot-fee-waivers.index')
            ->with('status', 'pilot-fee-waiver-entrepreneur-updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(Client $client): array
    {
        $eligibility = $this->waivers->eligibility($client);

        return [
            'key' => 'client:'.$client->getKey(),
            'subject_type' => 'client',
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
    private function entrepreneurPayload(EntrepreneurProfile $profile): array
    {
        $eligibility = $this->waivers->eligibility($profile);

        return [
            'key' => 'entrepreneur:'.$profile->getKey(),
            'subject_type' => 'entrepreneur',
            'id' => $profile->id,
            'legal_name' => $profile->name,
            'trading_name' => $profile->email,
            'engagement_type' => 'Entrepreneur',
            'enabled' => (bool) $profile->pilot_fee_waiver_enabled,
            'starts_at' => $profile->pilot_fee_waiver_starts_at?->toIso8601String(),
            'expires_at' => $profile->pilot_fee_waiver_expires_at?->toIso8601String(),
            'reason' => $profile->pilot_fee_waiver_reason,
            'approved_by_name' => $profile->pilotFeeWaiverApprovedBy?->name,
            'approved_at' => $profile->pilot_fee_waiver_approved_at?->toIso8601String(),
            'active_for_new_proposals' => $eligibility['eligible'],
            'update_url' => route(
                'admin.pilot-fee-waivers.entrepreneurs.update',
                $profile,
                absolute: false,
            ),
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
    private function clientAuditPayload(Client|EntrepreneurProfile $client): array
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

    /**
     * @return array{enabled:bool, starts_at:?string, expires_at:?string, reason:?string}
     */
    private function validatedWaiverAttributes(Request $request): array
    {
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

        return [
            'enabled' => $enabled,
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'reason' => $reason,
        ];
    }

    private function superAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_SUPER_ADMIN, 403);

        return $user;
    }
}
