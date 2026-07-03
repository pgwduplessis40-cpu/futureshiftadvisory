<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\InviteToken;
use App\Models\ProspectLead;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Security\InviteIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ProspectInboxController extends Controller
{
    public function __construct(private readonly AuditWriter $auditWriter) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', ProspectLead::class);

        return Inertia::render('advisor/prospects/Index', [
            'leads' => ProspectLead::query()
                ->with(['assignedAdvisor', 'triagedBy', 'inviteToken'])
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (ProspectLead $lead): array => $this->leadPayload($lead))
                ->values(),
            'targetUserTypes' => [
                User::TYPE_CLIENT_PRIMARY,
                User::TYPE_ENTREPRENEUR,
            ],
            'canTriage' => Gate::allows('triage', ProspectLead::class),
        ]);
    }

    public function triage(Request $request, ProspectLead $prospectLead, InviteIssuer $issuer): RedirectResponse
    {
        Gate::authorize('triage', $prospectLead);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $validated = $request->validate([
            'outcome' => ['required', 'string', Rule::in(ProspectLead::triageOutcomes())],
            'triage_notes' => ['nullable', 'string', 'max:2000'],
            'target_user_type' => [
                Rule::requiredIf(fn (): bool => $request->input('outcome') === ProspectLead::STATUS_INVITED),
                'nullable',
                'string',
                Rule::in([User::TYPE_CLIENT_PRIMARY, User::TYPE_ENTREPRENEUR]),
            ],
        ]);

        $before = $prospectLead->only([
            'status',
            'triage_outcome',
            'triage_notes',
            'triaged_at',
            'triaged_by_user_id',
            'invite_token_id',
        ]);

        DB::transaction(function () use ($actor, $issuer, $prospectLead, $validated, $before): void {
            $invite = $this->inviteForTriage($issuer, $prospectLead, $actor, $validated);

            $prospectLead->forceFill([
                'status' => $validated['outcome'],
                'triage_outcome' => $validated['outcome'],
                'triage_notes' => $validated['triage_notes'] ?? null,
                'triaged_at' => now(),
                'triaged_by_user_id' => $actor->getKey(),
                'assigned_advisor_user_id' => $prospectLead->assigned_advisor_user_id ?? $actor->getKey(),
                'invite_token_id' => $invite?->getKey() ?? $prospectLead->invite_token_id,
            ])->save();

            $this->auditWriter->record(
                action: 'prospect_lead.triaged',
                subject: $prospectLead,
                actor: $actor,
                before: $before,
                after: $prospectLead->only([
                    'status',
                    'triage_outcome',
                    'triage_notes',
                    'triaged_at',
                    'triaged_by_user_id',
                    'invite_token_id',
                ]),
            );
        });

        return to_route('advisor.prospects.index')->with('status', 'prospect-triaged');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function inviteForTriage(
        InviteIssuer $issuer,
        ProspectLead $lead,
        User $actor,
        array $validated,
    ): ?InviteToken {
        if ($validated['outcome'] !== ProspectLead::STATUS_INVITED || $lead->invite_token_id !== null) {
            return null;
        }

        $targetUserType = (string) ($validated['target_user_type'] ?? User::TYPE_CLIENT_PRIMARY);

        return $issuer->issue(
            email: $lead->email,
            targetUserType: $targetUserType,
            targetRole: $targetUserType,
            issuedBy: $actor,
            deliver: true,
        )->invite;
    }

    /**
     * @return array<string, mixed>
     */
    private function leadPayload(ProspectLead $lead): array
    {
        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'company' => $lead->company,
            'engagement_interest' => $lead->engagement_interest,
            'message' => $lead->message,
            'source' => $lead->source,
            'status' => $lead->status ?? ProspectLead::STATUS_NEW,
            'triage_outcome' => $lead->triage_outcome,
            'triage_notes' => $lead->triage_notes,
            'triaged_at' => $lead->triaged_at?->toIso8601String(),
            'created_at' => $lead->created_at?->toIso8601String(),
            'assigned_advisor_name' => $lead->assignedAdvisor?->name,
            'triaged_by_name' => $lead->triagedBy?->name,
            'invite_status' => $lead->inviteToken?->accepted_at === null
                ? ($lead->inviteToken instanceof InviteToken ? 'pending' : null)
                : 'accepted',
            'triage_url' => route('advisor.prospects.triage', $lead, absolute: false),
        ];
    }
}
