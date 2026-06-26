<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\InviteToken;
use App\Models\PanelAgreement;
use App\Models\PanelMember;
use App\Models\Referral;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Security\InviteIssuer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PartnerPanelController extends Controller
{
    public function __construct(
        private readonly AuditWriter $auditWriter,
        private readonly InviteIssuer $inviteIssuer,
    ) {}

    /**
     * @var array<int, string>
     */
    private const CLOSED_REFERRAL_STAGES = [
        Referral::STAGE_COMPLETED,
        Referral::STAGE_WITHDRAWN,
        Referral::STAGE_BROKER_COVER_PLACED,
        Referral::STAGE_BROKER_DECLINED,
        Referral::STAGE_BROKER_NO_RESPONSE,
        Referral::STAGE_COACH_CONCLUDED,
        Referral::STAGE_COACH_DECLINED,
    ];

    public function brokers(): Response
    {
        return $this->index(PanelMember::TYPE_BROKER);
    }

    public function createBroker(): Response
    {
        return $this->create(PanelMember::TYPE_BROKER);
    }

    public function storeBroker(Request $request): RedirectResponse
    {
        return $this->store($request, PanelMember::TYPE_BROKER);
    }

    public function coaches(): Response
    {
        return $this->index(PanelMember::TYPE_COACH);
    }

    public function createCoach(): Response
    {
        return $this->create(PanelMember::TYPE_COACH);
    }

    public function storeCoach(Request $request): RedirectResponse
    {
        return $this->store($request, PanelMember::TYPE_COACH);
    }

    public function resendInvite(Request $request, PanelMember $panelMember): RedirectResponse
    {
        $this->assertPartner($panelMember);
        $panelMember->loadMissing(['inviteToken', 'user']);

        if (! $this->canResendInvite($panelMember)) {
            return back()->withErrors([
                'invite' => 'Only pending or cancelled partner invites can be resent.',
            ]);
        }

        $actor = $this->actor($request);
        $email = $this->inviteEmail($panelMember);

        DB::transaction(function () use ($actor, $email, $panelMember): void {
            $previousInvite = $panelMember->inviteToken;

            if ($previousInvite instanceof InviteToken && ! $previousInvite->isAccepted()) {
                $previousInvite->forceFill([
                    'expires_at' => now()->subMinute(),
                ])->save();
            }

            $issued = $this->inviteIssuer->issue(
                email: $email,
                targetUserType: $panelMember->panel_type,
                targetRole: $panelMember->panel_type,
                issuedBy: $actor,
            );

            $application = $panelMember->application ?? [];
            $panelMember->forceFill([
                'invite_token_id' => $issued->invite->getKey(),
                'status' => PanelMember::STATUS_INVITED,
                'application' => [
                    ...$application,
                    'last_invite_resent_at' => now()->toIso8601String(),
                ],
            ])->save();

            $this->auditWriter->record('panel.invite_resent', subject: $panelMember, actor: $actor, after: [
                'panel_member_id' => $panelMember->getKey(),
                'panel_type' => $panelMember->panel_type,
                'email' => $email,
                'previous_invite_token_id' => $previousInvite?->getKey(),
                'invite_token_id' => $issued->invite->getKey(),
            ]);
        });

        return to_route('advisor.partners.show', $panelMember)
            ->with('status', $panelMember->panel_type.'-invite-resent');
    }

    public function cancelInvite(Request $request, PanelMember $panelMember): RedirectResponse
    {
        $this->assertPartner($panelMember);
        $panelMember->loadMissing(['inviteToken', 'user']);

        if (! $this->canCancelInvite($panelMember)) {
            return back()->withErrors([
                'invite' => 'Only pending partner invites can be cancelled.',
            ]);
        }

        $actor = $this->actor($request);
        $email = $this->inviteEmail($panelMember);

        DB::transaction(function () use ($actor, $email, $panelMember): void {
            $invite = $panelMember->inviteToken;

            if ($invite instanceof InviteToken && ! $invite->isAccepted()) {
                $invite->forceFill([
                    'expires_at' => now()->subMinute(),
                ])->save();
            }

            $application = $panelMember->application ?? [];
            $panelMember->forceFill([
                'status' => PanelMember::STATUS_CANCELLED,
                'application' => [
                    ...$application,
                    'invite_cancelled_at' => now()->toIso8601String(),
                    'invite_cancelled_by_user_id' => $actor->getKey(),
                ],
            ])->save();

            $this->auditWriter->record('panel.invite_cancelled', subject: $panelMember, actor: $actor, after: [
                'panel_member_id' => $panelMember->getKey(),
                'panel_type' => $panelMember->panel_type,
                'email' => $email,
                'invite_token_id' => $invite?->getKey(),
            ]);
        });

        return to_route('advisor.partners.show', $panelMember)
            ->with('status', $panelMember->panel_type.'-invite-cancelled');
    }

    public function show(PanelMember $panelMember): Response
    {
        $this->assertPartner($panelMember);

        $panelMember->load([
            'user',
            'inviteToken',
            'agreements' => fn ($query) => $query->latest('created_at')->limit(5),
            'referrals' => fn ($query) => $query
                ->with(['client', 'entrepreneurProfile'])
                ->latest('created_at')
                ->limit(8),
            'reverseReferrals' => fn ($query) => $query
                ->latest('submitted_at')
                ->limit(8),
        ])->loadCount([
            'referrals',
            'referrals as active_referrals_count' => $this->activeReferrals(...),
            'reverseReferrals',
        ]);
        $inviteDraft = $this->inviteIssuer->draftFor($panelMember->inviteToken);
        $accountOnboarded = $panelMember->user instanceof User;

        return Inertia::render('advisor/partners/Show', [
            'partner' => [
                ...$this->summary($panelMember),
                'email' => $panelMember->user?->email ?? $panelMember->inviteToken?->email,
                'invite_accepted_at' => $panelMember->inviteToken?->accepted_at?->toISOString(),
                'invite_expires_at' => $panelMember->inviteToken?->expires_at?->toISOString(),
                'account_status_label' => $accountOnboarded ? 'Account onboarded' : 'Invite pending',
                'invite_acceptance_label' => $accountOnboarded
                    ? 'Accepted'
                    : ($panelMember->inviteToken?->accepted_at?->toISOString() ?? null),
                'invite_expiry_label' => $accountOnboarded
                    ? 'No longer applicable'
                    : ($panelMember->inviteToken?->expires_at?->toISOString() ?? null),
                'invite_delivery_label' => $accountOnboarded
                    ? 'Account onboarded'
                    : ($inviteDraft['accept_url'] ?? null ? 'Manual Outlook send' : 'No active link'),
                'invite_accept_url' => $inviteDraft['accept_url'] ?? null,
                'invite_email_subject' => $inviteDraft['subject'] ?? null,
                'invite_email_body' => $inviteDraft['body'] ?? null,
                'invite_resend_url' => $this->canResendInvite($panelMember)
                    ? route('advisor.partners.invite.resend', $panelMember, absolute: false)
                    : null,
                'invite_cancel_url' => $this->canCancelInvite($panelMember)
                    ? route('advisor.partners.invite.cancel', $panelMember, absolute: false)
                    : null,
                'fsp_number' => $panelMember->fsp_number,
                'fsp_status' => $panelMember->fsp_status,
                'fsp_last_checked_at' => $panelMember->fsp_last_checked_at?->toISOString(),
                'applied_at' => $panelMember->applied_at?->toISOString(),
                'approved_at' => $panelMember->approved_at?->toISOString(),
                'suspended_at' => $panelMember->suspended_at?->toISOString(),
                'coach_profile' => $panelMember->coach_profile ?? [],
                'professional_memberships' => $this->stringList($panelMember->professional_memberships),
                'latest_agreement' => $this->latestAgreement($panelMember),
                'recent_referrals' => $panelMember->referrals
                    ->map(fn (Referral $referral): array => $this->referralSummary($referral))
                    ->values(),
                'reverse_referrals' => $panelMember->reverseReferrals
                    ->map(fn ($referral): array => [
                        'id' => $referral->id,
                        'name' => $referral->name,
                        'company' => $referral->company,
                        'target_type' => $this->headline($referral->target_type),
                        'submitted_at' => $referral->submitted_at?->toISOString(),
                    ])
                    ->values(),
                'back_url' => $this->indexUrl($panelMember->panel_type),
            ],
        ]);
    }

    private function index(string $panelType): Response
    {
        $partners = PanelMember::query()
            ->with(['user', 'inviteToken'])
            ->withCount([
                'referrals',
                'referrals as active_referrals_count' => $this->activeReferrals(...),
            ])
            ->where('panel_type', $panelType)
            ->latest('updated_at')
            ->get();

        $partners = $this->visiblePanelMembers($partners)
            ->sortBy(fn (PanelMember $member): string => $this->businessName($member))
            ->map(fn (PanelMember $member): array => $this->summary($member))
            ->values();

        return Inertia::render('advisor/partners/Index', [
            'title' => $panelType === PanelMember::TYPE_BROKER ? 'Brokers' : 'Coaches',
            'description' => $panelType === PanelMember::TYPE_BROKER
                ? 'Broker partners available for client referral and insurance support.'
                : 'Coach partners available for founder and leadership support.',
            'panelType' => $panelType,
            'panelLabel' => $this->panelLabel($panelType),
            'industryColumnLabel' => $panelType === PanelMember::TYPE_BROKER ? 'Industry' : 'Focus',
            'createUrl' => $this->createUrl($panelType),
            'partners' => $partners,
        ]);
    }

    /**
     * @param  Collection<int, PanelMember>  $members
     * @return Collection<int, PanelMember>
     */
    private function visiblePanelMembers(Collection $members): Collection
    {
        return $members
            ->groupBy(fn (PanelMember $member): string => $this->emailKey($member))
            ->flatMap(function (Collection $group, string $email): Collection {
                if ($email === '') {
                    return $group;
                }

                $visible = $group->reject(
                    fn (PanelMember $member): bool => filled(data_get($member->application, 'superseded_by_panel_member_id')),
                );

                if ($visible->count() <= 1) {
                    return $visible;
                }

                $hasCurrentRecord = $visible->contains(
                    fn (PanelMember $member): bool => $member->user_id !== null
                        || ! in_array($member->status, [PanelMember::STATUS_INVITED, PanelMember::STATUS_CANCELLED], true),
                );

                if ($hasCurrentRecord) {
                    $visible = $visible->reject(
                        fn (PanelMember $member): bool => $member->user_id === null
                            && in_array($member->status, [PanelMember::STATUS_INVITED, PanelMember::STATUS_CANCELLED], true),
                    );
                }

                if ($visible->isEmpty()) {
                    return $group->sortByDesc('updated_at')->take(1);
                }

                return $visible;
            })
            ->values();
    }

    private function create(string $panelType): Response
    {
        return Inertia::render('advisor/partners/Create', [
            'title' => 'Invite '.strtolower($this->panelLabel($panelType)),
            'panelType' => $panelType,
            'panelLabel' => $this->panelLabel($panelType),
            'backUrl' => $this->indexUrl($panelType),
            'storeUrl' => $this->storeUrl($panelType),
            'industryOptions' => $panelType === PanelMember::TYPE_BROKER
                ? [
                    ['value' => 'business_insurance', 'label' => 'Business insurance'],
                    ['value' => 'life_insurance', 'label' => 'Life insurance'],
                ]
                : [],
        ]);
    }

    private function store(Request $request, string $panelType): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'industry' => [
                Rule::requiredIf($panelType === PanelMember::TYPE_BROKER),
                'nullable',
                'string',
                Rule::in(['business_insurance', 'life_insurance']),
            ],
            'focus' => [
                Rule::requiredIf($panelType === PanelMember::TYPE_COACH),
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $email = str($validated['email'])->lower()->trim()->toString();
        $this->ensurePartnerInviteIsNew($panelType, $email);

        $issued = $this->inviteIssuer->issue(
            email: $email,
            targetUserType: $panelType,
            targetRole: $panelType,
            issuedBy: $request->user(),
        );

        PanelMember::query()->create([
            'invite_token_id' => $issued->invite->getKey(),
            'panel_type' => $panelType,
            'status' => PanelMember::STATUS_INVITED,
            'application' => $this->inviteApplicationPayload($panelType, $validated),
        ]);

        return redirect($this->indexUrl($panelType))->with('status', strtolower($this->panelLabel($panelType)).'-invited');
    }

    private function ensurePartnerInviteIsNew(string $panelType, string $email): void
    {
        $existing = PanelMember::query()
            ->where('panel_type', $panelType)
            ->where(function (Builder $query) use ($email): void {
                $query
                    ->whereHas('user', fn (Builder $userQuery) => $userQuery->where('email', $email))
                    ->orWhereHas('inviteToken', fn (Builder $inviteQuery) => $inviteQuery->where('email', $email));
            })
            ->exists();

        if ($existing || User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email address already has an invite or panel record.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function inviteApplicationPayload(string $panelType, array $validated): array
    {
        $payload = [
            'company' => $validated['business_name'],
            'contact_name' => $validated['contact_name'],
            'notes' => $validated['notes'] ?? null,
            'invited_at' => now()->toIso8601String(),
        ];

        if ($panelType === PanelMember::TYPE_BROKER) {
            return [
                ...$payload,
                'broker_name' => $validated['contact_name'],
                'industry' => $validated['industry'] === 'life_insurance'
                    ? 'Life insurance'
                    : 'Business insurance',
            ];
        }

        return [
            ...$payload,
            'coach_name' => $validated['contact_name'],
            'focus' => $validated['focus'],
            'specialties' => [$validated['focus']],
        ];
    }

    private function activeReferrals(Builder $query): void
    {
        $query
            ->whereNull('closed_at')
            ->whereNotIn('stage', self::CLOSED_REFERRAL_STAGES);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(PanelMember $member): array
    {
        return [
            'id' => $member->id,
            'panel_type' => $member->panel_type,
            'panel_label' => $this->panelLabel($member->panel_type),
            'business_name' => $this->businessName($member),
            'contact_name' => $this->contactName($member),
            'email' => $member->user?->email ?? $member->inviteToken?->email,
            'status' => $member->status,
            'status_label' => $this->headline($member->status),
            'industry_label' => $this->industryLabel($member),
            'regions' => $this->stringList(data_get($member->application, 'regions')),
            'specialties' => $this->stringList(data_get($member->application, 'specialties') ?? $member->coach_specialisations),
            'referrals_count' => (int) ($member->referrals_count ?? 0),
            'active_referrals_count' => (int) ($member->active_referrals_count ?? 0),
            'reverse_referrals_count' => (int) ($member->reverse_referrals_count ?? 0),
            'show_url' => route('advisor.partners.show', $member, absolute: false),
        ];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function assertPartner(PanelMember $panelMember): void
    {
        abort_unless(in_array($panelMember->panel_type, PanelMember::panelTypes(), true), 404);
    }

    private function canResendInvite(PanelMember $panelMember): bool
    {
        return $panelMember->user_id === null
            && $panelMember->user === null
            && in_array($panelMember->status, [PanelMember::STATUS_INVITED, PanelMember::STATUS_CANCELLED], true)
            && ! $panelMember->inviteToken?->isAccepted()
            && filter_var($this->inviteEmail($panelMember), FILTER_VALIDATE_EMAIL) !== false;
    }

    private function canCancelInvite(PanelMember $panelMember): bool
    {
        return $panelMember->user_id === null
            && $panelMember->user === null
            && $panelMember->status === PanelMember::STATUS_INVITED
            && $panelMember->inviteToken instanceof InviteToken
            && ! $panelMember->inviteToken->isAccepted()
            && filter_var($this->inviteEmail($panelMember), FILTER_VALIDATE_EMAIL) !== false;
    }

    private function inviteEmail(PanelMember $panelMember): string
    {
        return (string) ($panelMember->user?->email ?? $panelMember->inviteToken?->email);
    }

    private function emailKey(PanelMember $panelMember): string
    {
        return strtolower(trim($this->inviteEmail($panelMember)));
    }

    private function businessName(PanelMember $member): string
    {
        $application = $member->application ?? [];

        return (string) (
            data_get($application, 'business_name')
            ?? data_get($application, 'company')
            ?? data_get($application, 'company_name')
            ?? data_get($application, 'practice_name')
            ?? $member->user?->name
            ?? 'Unnamed partner'
        );
    }

    private function contactName(PanelMember $member): string
    {
        $application = $member->application ?? [];

        return (string) (
            data_get($application, 'broker_name')
            ?? data_get($application, 'coach_name')
            ?? data_get($application, 'contact_name')
            ?? data_get($application, 'name')
            ?? $member->user?->name
            ?? 'Unknown contact'
        );
    }

    private function industryLabel(PanelMember $member): string
    {
        $application = $member->application ?? [];

        $explicit = data_get($application, 'industry')
            ?? data_get($application, 'insurance_industry')
            ?? data_get($application, 'insurance_type')
            ?? data_get($application, 'focus');

        if (is_string($explicit) && trim($explicit) !== '') {
            return $this->normaliseIndustry($explicit);
        }

        if ($member->panel_type === PanelMember::TYPE_COACH) {
            $specialisations = $this->stringList($member->coach_specialisations);

            return implode(', ', $specialisations) ?: 'Coaching';
        }

        $specialties = strtolower(implode(' ', $this->stringList(data_get($application, 'specialties'))));

        if (str_contains($specialties, 'life')) {
            return 'Life insurance';
        }

        return 'Business insurance';
    }

    private function normaliseIndustry(string $value): string
    {
        $lower = strtolower($value);

        if (str_contains($lower, 'life')) {
            return 'Life insurance';
        }

        if (str_contains($lower, 'business') || str_contains($lower, 'commercial')) {
            return 'Business insurance';
        }

        return $this->headline($value);
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            $value = [];
        }

        return array_values(array_filter(array_map(
            static fn ($item): string => is_scalar($item) ? trim((string) $item) : '',
            $value,
        )));
    }

    private function panelLabel(string $panelType): string
    {
        return $panelType === PanelMember::TYPE_BROKER ? 'Broker' : 'Coach';
    }

    private function headline(string $value): string
    {
        return str((string) $value)
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }

    private function latestAgreement(PanelMember $member): ?array
    {
        $agreement = $member->agreements->first();

        if (! $agreement instanceof PanelAgreement) {
            return null;
        }

        return [
            'id' => $agreement->id,
            'status' => $agreement->status,
            'status_label' => $this->headline($agreement->status),
            'generated_at' => $agreement->generated_at?->toISOString(),
            'signed_at' => $agreement->signed_at?->toISOString(),
            'terms' => $agreement->terms ?? [],
            'download_url' => filled($agreement->pdf_path)
                ? route('panel.agreements.download', $agreement, absolute: false)
                : null,
        ];
    }

    private function referralSummary(Referral $referral): array
    {
        return [
            'id' => $referral->id,
            'stage' => $referral->stage,
            'stage_label' => $this->headline($referral->stage),
            'referral_type' => $this->headline($referral->referral_type),
            'subject' => $referral->client?->legal_name
                ?? $referral->entrepreneurProfile?->name
                ?? 'Referral subject',
            'sent_at' => $referral->sent_at?->toISOString(),
            'closed_at' => $referral->closed_at?->toISOString(),
        ];
    }

    private function indexUrl(string $panelType): string
    {
        return $panelType === PanelMember::TYPE_BROKER
            ? route('advisor.partners.brokers.index', absolute: false)
            : route('advisor.partners.coaches.index', absolute: false);
    }

    private function createUrl(string $panelType): string
    {
        return $panelType === PanelMember::TYPE_BROKER
            ? route('advisor.partners.brokers.create', absolute: false)
            : route('advisor.partners.coaches.create', absolute: false);
    }

    private function storeUrl(string $panelType): string
    {
        return $panelType === PanelMember::TYPE_BROKER
            ? route('advisor.partners.brokers.store', absolute: false)
            : route('advisor.partners.coaches.store', absolute: false);
    }
}
