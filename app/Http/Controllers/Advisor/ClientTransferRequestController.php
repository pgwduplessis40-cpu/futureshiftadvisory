<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AdvisorClientTransferRequest;
use App\Models\Client;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ClientTransferRequestController extends Controller
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function index(Request $request): Response
    {
        $advisor = $this->advisor($request);
        $clientIds = $advisor->accessibleClientIds();

        return Inertia::render('advisor/client-transfers/Index', [
            'clients' => Client::query()
                ->whereIn('id', $clientIds)
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'trading_name', 'engagement_type'])
                ->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'label' => $client->trading_name ?: $client->legal_name,
                    'engagement_type' => is_string($client->engagement_type)
                        ? $client->engagement_type
                        : $client->engagement_type->label(),
                ])
                ->values(),
            'advisors' => $this->transferTargets($advisor),
            'pendingRequests' => AdvisorClientTransferRequest::query()
                ->with(['client:id,legal_name,trading_name', 'targetAdvisor:id,name'])
                ->where('requested_by_user_id', $advisor->getKey())
                ->where('status', AdvisorClientTransferRequest::STATUS_PENDING)
                ->latest()
                ->get()
                ->map(fn (AdvisorClientTransferRequest $transfer): array => [
                    'id' => $transfer->id,
                    'client_label' => $transfer->client?->trading_name ?: $transfer->client?->legal_name,
                    'target_advisor_name' => $transfer->targetAdvisor?->name,
                    'reason' => $transfer->reason,
                    'created_at' => $transfer->created_at?->toIso8601String(),
                ])
                ->values(),
            'defaults' => [
                'client_id' => (string) $request->query('client_id', ''),
                'target_advisor_id' => '',
                'reason' => '',
            ],
            'storeUrl' => route('advisor.client-transfers.store', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $advisor = $this->advisor($request);
        $validated = $request->validate([
            'client_id' => ['required', 'uuid'],
            'target_advisor_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $client = Client::query()
            ->whereIn('id', $advisor->accessibleClientIds())
            ->findOrFail($validated['client_id']);
        $targetAdvisor = User::query()
            ->whereKey($validated['target_advisor_id'])
            ->where('user_type', User::TYPE_ADVISOR)
            ->whereNull('suspended_at')
            ->firstOrFail();

        if ((string) $targetAdvisor->getKey() === (string) $advisor->getKey()) {
            throw ValidationException::withMessages([
                'target_advisor_id' => 'Choose a different advisor for this transfer.',
            ]);
        }

        $alreadyPending = AdvisorClientTransferRequest::query()
            ->where('client_id', $client->getKey())
            ->where('status', AdvisorClientTransferRequest::STATUS_PENDING)
            ->exists();

        if ($alreadyPending) {
            throw ValidationException::withMessages([
                'client_id' => 'A transfer request for this client is already awaiting review.',
            ]);
        }

        $transfer = AdvisorClientTransferRequest::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $advisor->getKey(),
            'target_advisor_user_id' => $targetAdvisor->getKey(),
            'reason' => trim($validated['reason']),
            'status' => AdvisorClientTransferRequest::STATUS_PENDING,
        ]);

        $this->audit->record('advisor_client_transfer.requested', subject: $transfer, actor: $advisor, after: [
            'client_id' => $client->getKey(),
            'target_advisor_user_id' => $targetAdvisor->getKey(),
            'reason' => $transfer->reason,
        ]);

        return to_route('advisor.client-transfers.index')->with('status', 'client-transfer-requested');
    }

    private function advisor(Request $request): User
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && in_array($user->user_type, [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR], true),
            403,
        );
        abort_unless($user->can(Permission::CLIENTS_VIEW->value), 403);

        return $user;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function transferTargets(User $advisor): array
    {
        return User::query()
            ->where('user_type', User::TYPE_ADVISOR)
            ->whereNull('suspended_at')
            ->whereKeyNot($advisor->getKey())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values()
            ->all();
    }
}
