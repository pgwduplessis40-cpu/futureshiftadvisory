<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\PanelAgreement;
use App\Models\PanelMember;
use App\Models\Referral;
use App\Models\User;
use App\Services\Security\InviteIssuer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PartnerPanelController extends Controller
{
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

    public function storeBroker(Request $request, InviteIssuer $issuer): RedirectResponse
    {
        return $this->store($request, $issuer, PanelMember::TYPE_BROKER);
    }

    public function coaches(): Response
    {
        return $this->index(PanelMember::TYPE_COACH);
    }

    public function createCoach(): Response
    {
        return $this->create(PanelMember::TYPE_COACH);
    }

    public function storeCoach(Request $request, InviteIssuer $issuer): RedirectResponse
    {
        return $this->store($request, $issuer, PanelMember::TYPE_COACH);
    }

    public function show(PanelMember $panelMember): Response
    {
        abort_unless(in_array($panelMember->panel_type, PanelMember::panelTypes(), true), 404);

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

        return Inertia::render('advisor/partners/Show', [
            'partner' => [
                ...$this->summary($panelMember),
                'email' => $panelMember->user?->email ?? $panelMember->inviteToken?->email,
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
            ->get()
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

    private function store(Request $request, InviteIssuer $issuer, string $panelType): RedirectResponse
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

        $issued = $issuer->issue(
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
