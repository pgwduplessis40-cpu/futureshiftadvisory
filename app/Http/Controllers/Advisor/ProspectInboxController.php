<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\InviteToken;
use App\Models\ProspectLead;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
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
            'inviteOptions' => array_values($this->invitePathOptions()),
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
            'invite_path' => [
                Rule::requiredIf(fn (): bool => $request->input('outcome') === ProspectLead::STATUS_INVITED),
                'nullable',
                'string',
                Rule::in(array_keys($this->invitePathOptions())),
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

        $option = $this->invitePathOption((string) ($validated['invite_path'] ?? ''));
        $targetUserType = (string) $option['target_user_type'];

        return $issuer->issue(
            email: $lead->email,
            targetUserType: $targetUserType,
            targetRole: $targetUserType,
            intendedServiceType: (string) $option['intended_service_type'],
            intendedPackageScope: $option['intended_package_scope'],
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
            'invite_path_label' => $lead->inviteToken?->serviceIntentLabel(),
            'invite_package_scope_label' => $lead->inviteToken instanceof InviteToken
                ? ServiceRatePackage::packageScopeLabel($lead->inviteToken->intended_package_scope)
                : null,
        ];
    }

    /**
     * @return array<string, array{value:string,label:string,description:string,target_user_type:string,intended_service_type:string,intended_package_scope:string|null}>
     */
    private function invitePathOptions(): array
    {
        return [
            'business_idea' => [
                'value' => 'business_idea',
                'label' => 'Business Idea',
                'description' => 'Creates an entrepreneur account and opens the idea-validation path first.',
                'target_user_type' => User::TYPE_ENTREPRENEUR,
                'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
                'intended_package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            ],
            'buying_business' => [
                'value' => 'buying_business',
                'label' => 'Buying a Business',
                'description' => 'Creates a client-primary account for the buying-a-business/DD access path.',
                'target_user_type' => User::TYPE_CLIENT_PRIMARY,
                'intended_service_type' => ServiceActivation::SERVICE_DUE_DILIGENCE,
                'intended_package_scope' => null,
            ],
        ];
    }

    /**
     * @return array{value:string,label:string,description:string,target_user_type:string,intended_service_type:string,intended_package_scope:string|null}
     */
    private function invitePathOption(string $value): array
    {
        $options = $this->invitePathOptions();

        return $options[$value] ?? $options['business_idea'];
    }
}
