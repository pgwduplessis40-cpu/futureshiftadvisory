<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvisorClientTransferRequest;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Clients\AdvisorClientCapacity;
use App\Services\Clients\AdvisorTeamManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ClientAllocationController extends Controller
{
    public function __construct(private readonly AdvisorClientCapacity $clientCapacity) {}

    public function index(): Response
    {
        return Inertia::render('admin/client-allocations/Index', [
            'clients' => Client::query()
                ->with([
                    'teamMembers' => fn ($query) => $query
                        ->whereIn('role', ['lead_advisor', 'advisor'])
                        ->with(['user:id,name', 'advisorTeam:id,name']),
                ])
                ->orderBy('legal_name')
                ->limit(200)
                ->get()
                ->map(fn (Client $client): array => $this->clientPayload($client))
                ->values(),
            'advisors' => User::query()
                ->whereIn('user_type', [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR])
                ->whereNull('suspended_at')
                ->orderBy('name')
                ->get()
                ->map(fn (User $advisor): array => [
                    'id' => $advisor->id,
                    'name' => $advisor->name,
                    'user_type' => $advisor->user_type,
                    'capacity' => $this->clientCapacity->summary($advisor),
                ])
                ->values(),
            'pendingRequests' => AdvisorClientTransferRequest::query()
                ->with([
                    'client:id,legal_name,trading_name',
                    'requestedBy:id,name',
                    'targetAdvisor:id,name',
                ])
                ->where('status', AdvisorClientTransferRequest::STATUS_PENDING)
                ->latest()
                ->get()
                ->map(fn (AdvisorClientTransferRequest $transfer): array => [
                    'id' => $transfer->id,
                    'client_label' => $transfer->client?->trading_name ?: $transfer->client?->legal_name,
                    'requested_by_name' => $transfer->requestedBy?->name,
                    'target_advisor_name' => $transfer->targetAdvisor?->name,
                    'reason' => $transfer->reason,
                    'created_at' => $transfer->created_at?->toIso8601String(),
                    'approve_url' => route('admin.client-transfers.approve', $transfer, absolute: false),
                    'reject_url' => route('admin.client-transfers.reject', $transfer, absolute: false),
                ])
                ->values(),
        ]);
    }

    public function reassign(
        Request $request,
        Client $client,
        AdvisorTeamManager $teams,
    ): RedirectResponse {
        $actor = $this->superAdmin($request);
        $validated = $request->validate([
            'target_advisor_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $targetAdvisor = $this->targetAdvisor($validated['target_advisor_id']);

        $teams->reassignClientToAdvisor(
            client: $client,
            targetAdvisor: $targetAdvisor,
            actor: $actor,
            reason: trim($validated['reason']),
        );

        return to_route('admin.client-allocations.index')->with('status', 'client-reassigned');
    }

    public function approve(
        Request $request,
        AdvisorClientTransferRequest $transfer,
        AdvisorTeamManager $teams,
        AuditWriter $audit,
    ): RedirectResponse {
        $actor = $this->superAdmin($request);
        abort_unless($transfer->status === AdvisorClientTransferRequest::STATUS_PENDING, 422);

        $request->validate([
            'decision_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $transfer->loadMissing(['client', 'targetAdvisor']);
        abort_unless($transfer->client instanceof Client && $transfer->targetAdvisor instanceof User, 404);

        $teams->reassignClientToAdvisor(
            client: $transfer->client,
            targetAdvisor: $transfer->targetAdvisor,
            actor: $actor,
            reason: $transfer->reason,
        );

        $transfer->forceFill([
            'status' => AdvisorClientTransferRequest::STATUS_APPROVED,
            'decision_reason' => trim((string) $request->input('decision_reason')) ?: null,
            'reviewed_by_user_id' => $actor->getKey(),
            'reviewed_at' => now(),
            'completed_at' => now(),
        ])->save();

        $audit->record('advisor_client_transfer.approved', subject: $transfer, actor: $actor, after: [
            'client_id' => $transfer->client_id,
            'target_advisor_user_id' => $transfer->target_advisor_user_id,
        ]);

        return to_route('admin.client-allocations.index')->with('status', 'client-transfer-approved');
    }

    public function reject(
        Request $request,
        AdvisorClientTransferRequest $transfer,
        AuditWriter $audit,
    ): RedirectResponse {
        $actor = $this->superAdmin($request);
        abort_unless($transfer->status === AdvisorClientTransferRequest::STATUS_PENDING, 422);
        $validated = $request->validate([
            'decision_reason' => ['required', 'string', 'max:2000'],
        ]);

        $transfer->forceFill([
            'status' => AdvisorClientTransferRequest::STATUS_REJECTED,
            'decision_reason' => trim($validated['decision_reason']),
            'reviewed_by_user_id' => $actor->getKey(),
            'reviewed_at' => now(),
        ])->save();

        $audit->record('advisor_client_transfer.rejected', subject: $transfer, actor: $actor, after: [
            'client_id' => $transfer->client_id,
            'target_advisor_user_id' => $transfer->target_advisor_user_id,
            'decision_reason' => $transfer->decision_reason,
        ]);

        return to_route('admin.client-allocations.index')->with('status', 'client-transfer-rejected');
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(Client $client): array
    {
        $assignments = $client->teamMembers
            ->map(fn (ClientTeamMember $member): array => [
                'advisor_name' => $member->user?->name,
                'role' => $member->role,
                'team_name' => $member->advisorTeam?->name,
            ])
            ->values();
        $lead = $assignments->firstWhere('role', 'lead_advisor');

        return [
            'id' => $client->id,
            'legal_name' => $client->legal_name,
            'trading_name' => $client->trading_name,
            'engagement_type' => is_string($client->engagement_type)
                ? $client->engagement_type
                : $client->engagement_type->label(),
            'status' => is_string($client->status) ? $client->status : $client->status->label(),
            'primary_advisor_name' => $lead['advisor_name'] ?? null,
            'advisor_team_name' => $lead['team_name'] ?? null,
            'assignments' => $assignments,
            'reassign_url' => route('admin.client-allocations.reassign', $client, absolute: false),
        ];
    }

    private function superAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_SUPER_ADMIN, 403);

        return $user;
    }

    private function targetAdvisor(int $advisorId): User
    {
        $advisor = User::query()
            ->whereKey($advisorId)
            ->whereIn('user_type', [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR])
            ->whereNull('suspended_at')
            ->first();

        if (! $advisor instanceof User) {
            throw ValidationException::withMessages([
                'target_advisor_id' => 'Select an active advisor as the new client owner.',
            ]);
        }

        return $advisor;
    }
}
