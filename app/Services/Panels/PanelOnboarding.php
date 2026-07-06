<?php

declare(strict_types=1);

namespace App\Services\Panels;

use App\Models\PanelAgreement;
use App\Models\PanelMember;
use App\Models\User;
use App\Notifications\PanelApplicationInformationRequestedNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Panels\Broker\BrokerFspVerifier;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PanelOnboarding
{
    public function __construct(
        private readonly PanelAgreementPdfRenderer $agreementRenderer,
        private readonly KeyEnvelope $envelope,
        private readonly AuditWriter $audit,
        private readonly BrokerFspVerifier $brokerFspVerifier,
    ) {}

    /**
     * @param  array<string, mixed>  $application
     */
    public function submitApplication(User $user, string $panelType, array $application): PanelMember
    {
        if (! in_array($panelType, PanelMember::panelTypes(), true)) {
            throw new InvalidArgumentException('Unsupported panel type.');
        }

        if ($panelType !== $user->user_type) {
            throw new InvalidArgumentException('Panel applications must match the user role.');
        }

        $member = DB::transaction(function () use ($application, $panelType, $user): PanelMember {
            $member = $this->applicationMember($user, $panelType);

            if (! $member instanceof PanelMember) {
                $member = new PanelMember([
                    'panel_type' => $panelType,
                ]);
            }

            $member->forceFill([
                'user_id' => $user->getKey(),
                'status' => PanelMember::STATUS_APPLICATION_PENDING,
                'application' => $application,
                'applied_at' => now(),
            ])->save();

            $member = $member->refresh()->load('inviteToken');
            $this->ensureInviteAcceptedByUser($member, $user);
            $this->supersedeDuplicateInvites($member, $user);

            return $member->refresh();
        });

        $this->audit->record('panel.application_submitted', subject: $member, actor: $user, after: [
            'panel_type' => $panelType,
            'status' => PanelMember::STATUS_APPLICATION_PENDING,
        ]);

        return $member->refresh();
    }

    private function applicationMember(User $user, string $panelType): ?PanelMember
    {
        $member = PanelMember::query()
            ->where('user_id', $user->getKey())
            ->where('panel_type', $panelType)
            ->latest('updated_at')
            ->first();

        if ($member instanceof PanelMember) {
            return $member;
        }

        return PanelMember::query()
            ->whereNull('user_id')
            ->where('panel_type', $panelType)
            ->whereHas('inviteToken', fn ($query) => $query
                ->where('target_user_type', $panelType)
                ->whereRaw('lower(email) = ?', [strtolower((string) $user->email)]))
            ->latest('updated_at')
            ->first();
    }

    private function supersedeDuplicateInvites(PanelMember $currentMember, User $user): void
    {
        PanelMember::query()
            ->with('inviteToken')
            ->whereKeyNot($currentMember->getKey())
            ->whereNull('user_id')
            ->where('panel_type', $currentMember->panel_type)
            ->whereIn('status', [PanelMember::STATUS_INVITED, PanelMember::STATUS_CANCELLED])
            ->whereHas('inviteToken', fn ($query) => $query
                ->where('target_user_type', $currentMember->panel_type)
                ->whereRaw('lower(email) = ?', [strtolower((string) $user->email)]))
            ->get()
            ->each(function (PanelMember $duplicate) use ($currentMember): void {
                $invite = $duplicate->inviteToken;
                if ($invite !== null && ! $invite->isAccepted()) {
                    $invite->forceFill([
                        'expires_at' => now()->subMinute(),
                    ])->save();
                }

                $application = $duplicate->application ?? [];
                $duplicate->forceFill([
                    'status' => PanelMember::STATUS_CANCELLED,
                    'application' => [
                        ...$application,
                        'superseded_at' => now()->toIso8601String(),
                        'superseded_by_panel_member_id' => $currentMember->getKey(),
                    ],
                ])->save();
            });
    }

    private function ensureInviteAcceptedByUser(PanelMember $member, User $user): void
    {
        $invite = $member->inviteToken;
        if ($invite === null) {
            return;
        }

        if (! $invite->isAccepted()) {
            $invite->forceFill([
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->getKey(),
            ])->save();

            return;
        }

        if ($invite->accepted_by_user_id === null) {
            $invite->forceFill([
                'accepted_by_user_id' => $user->getKey(),
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    public function approve(PanelMember $member, User $admin, array $terms = []): PanelAgreement
    {
        if (! in_array($admin->user_type, [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN], true)) {
            throw new InvalidArgumentException('Only advisors or super admins can approve panel members.');
        }

        return DB::transaction(function () use ($member, $admin, $terms): PanelAgreement {
            $member = $member->refresh();

            if ($member->panel_type === PanelMember::TYPE_BROKER) {
                $member = $this->brokerFspVerifier->validateForApproval($member, $admin);
            }

            $member->forceFill([
                'status' => PanelMember::STATUS_APPROVED_PENDING_AGREEMENT,
                'approved_by_user_id' => $admin->getKey(),
                'approved_at' => now(),
                'suspended_at' => null,
            ])->save();

            $agreement = PanelAgreement::query()->create([
                'panel_member_id' => $member->getKey(),
                'status' => PanelAgreement::STATUS_PENDING_SIGNATURE,
                'terms' => $this->terms($member, $terms),
                'generated_at' => now(),
            ]);

            $this->audit->record('panel.member_approved', subject: $member, actor: $admin, after: [
                'panel_type' => $member->panel_type,
                'agreement_id' => $agreement->getKey(),
            ]);

            return $agreement->refresh();
        });
    }

    public function requestMoreInformation(PanelMember $member, User $admin, string $reason): PanelMember
    {
        if (! in_array($admin->user_type, [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN], true)) {
            throw new InvalidArgumentException('Only advisors or super admins can request panel application information.');
        }

        $application = $member->application ?? [];
        $member->forceFill([
            'status' => PanelMember::STATUS_INFORMATION_REQUESTED,
            'application' => [
                ...$application,
                'review' => $this->applicationReviewPayload('information_requested', $admin, $reason),
            ],
        ])->save();

        $this->audit->record('panel.application_information_requested', subject: $member, actor: $admin, after: [
            'panel_type' => $member->panel_type,
            'reason' => $reason,
        ]);

        $member = $member->refresh()->loadMissing('user');

        if ($member->user instanceof User) {
            Notification::send($member->user, new PanelApplicationInformationRequestedNotification($member, $reason));
        }

        return $member;
    }

    public function decline(PanelMember $member, User $admin, string $reason): PanelMember
    {
        if (! in_array($admin->user_type, [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN], true)) {
            throw new InvalidArgumentException('Only advisors or super admins can decline panel applications.');
        }

        $application = $member->application ?? [];
        $member->forceFill([
            'status' => PanelMember::STATUS_DECLINED,
            'approved_by_user_id' => null,
            'approved_at' => null,
            'application' => [
                ...$application,
                'review' => $this->applicationReviewPayload('declined', $admin, $reason),
            ],
        ])->save();

        $this->audit->record('panel.application_declined', subject: $member, actor: $admin, after: [
            'panel_type' => $member->panel_type,
            'reason' => $reason,
        ]);

        return $member->refresh();
    }

    public function signAgreement(PanelAgreement $agreement, User $actor): PanelAgreement
    {
        return DB::transaction(function () use ($agreement, $actor): PanelAgreement {
            $agreement = $agreement->refresh()->loadMissing('panelMember.user');
            $member = $agreement->panelMember;

            if (! $member instanceof PanelMember) {
                throw new PanelAccessException('Panel agreement is not linked to a panel member.');
            }

            if ((string) $member->user_id !== (string) $actor->getKey()) {
                throw new PanelAccessException('Only the panel member can sign their panel agreement.');
            }

            if ($agreement->status !== PanelAgreement::STATUS_PENDING_SIGNATURE) {
                throw new InvalidArgumentException('Only pending panel agreements can be signed.');
            }

            $signedAt = now();
            $pdf = $this->agreementRenderer->renderPdf($agreement, $actor, $signedAt);
            $path = sprintf('panel/agreements/%s/%s-agreement.pdf', $member->getKey(), Str::uuid());

            if (Storage::disk('secure_local')->put($path, $pdf) !== true) {
                throw new \RuntimeException('Panel agreement PDF could not be stored.');
            }

            $hashEnvelope = $this->envelope->encrypt(hash('sha256', $pdf));

            $agreement->forceFill([
                'status' => PanelAgreement::STATUS_SIGNED,
                'pdf_path' => $path,
                'pdf_sha256_envelope' => $hashEnvelope,
                'pdf_envelope_meta' => $this->envelope->inspect($hashEnvelope),
                'pdf_byte_size' => strlen($pdf),
                'signed_by_user_id' => $actor->getKey(),
                'signed_at' => $signedAt,
            ])->save();

            $member->forceFill([
                'status' => PanelMember::STATUS_ACTIVE,
                'suspended_at' => null,
            ])->save();

            $this->audit->record('panel.agreement_signed', subject: $agreement, actor: $actor, after: [
                'panel_member_id' => $member->getKey(),
                'panel_type' => $member->panel_type,
                'pdf_path' => $path,
            ]);

            return $agreement->refresh();
        });
    }

    public function refreshSignedAgreementPdf(PanelAgreement $agreement): PanelAgreement
    {
        return DB::transaction(function () use ($agreement): PanelAgreement {
            $agreement = $agreement->refresh()->loadMissing(['panelMember.user', 'signedBy']);
            $member = $agreement->panelMember;

            if (! $member instanceof PanelMember) {
                throw new PanelAccessException('Panel agreement is not linked to a panel member.');
            }

            if ($agreement->status !== PanelAgreement::STATUS_SIGNED) {
                throw new InvalidArgumentException('Only signed panel agreements can be refreshed.');
            }

            if ($agreement->signed_at === null) {
                throw new InvalidArgumentException('Signed panel agreements require a signed timestamp before refresh.');
            }

            $actor = $agreement->signedBy instanceof User ? $agreement->signedBy : $member->user;

            if (! $actor instanceof User) {
                throw new PanelAccessException('Panel agreement is missing the signing user.');
            }

            $pdf = $this->agreementRenderer->renderPdf($agreement, $actor, $agreement->signed_at);
            $path = sprintf('panel/agreements/%s/%s-agreement.pdf', $member->getKey(), Str::uuid());

            if (Storage::disk('secure_local')->put($path, $pdf) !== true) {
                throw new \RuntimeException('Panel agreement PDF could not be stored.');
            }

            $hashEnvelope = $this->envelope->encrypt(hash('sha256', $pdf));
            $previousPath = $agreement->pdf_path;

            $agreement->forceFill([
                'pdf_path' => $path,
                'pdf_sha256_envelope' => $hashEnvelope,
                'pdf_envelope_meta' => $this->envelope->inspect($hashEnvelope),
                'pdf_byte_size' => strlen($pdf),
            ])->save();

            $this->audit->record('panel.agreement_pdf_refreshed', subject: $agreement, after: [
                'panel_member_id' => $member->getKey(),
                'panel_type' => $member->panel_type,
                'previous_pdf_path' => $previousPath,
                'pdf_path' => $path,
            ]);

            return $agreement->refresh();
        });
    }

    public function assertPortalAccess(User $user): PanelMember
    {
        $member = PanelMember::query()
            ->where('user_id', $user->getKey())
            ->where('panel_type', $user->user_type)
            ->latest()
            ->first();

        if (! $member instanceof PanelMember || $member->status !== PanelMember::STATUS_ACTIVE) {
            throw new PanelAccessException('Panel portal access requires an active signed agreement.');
        }

        if (! $member->agreements()
            ->where('status', PanelAgreement::STATUS_SIGNED)
            ->exists()) {
            throw new PanelAccessException('Panel portal access requires a signed agreement.');
        }

        return $member;
    }

    /**
     * @param  array<string, mixed>  $terms
     * @return array<string, mixed>
     */
    private function terms(PanelMember $member, array $terms): array
    {
        $baseTerms = [
            'panel_type' => $member->panel_type,
            'agreement_title' => (string) Config::get('panels.agreements.title', 'Future Shift Advisory partner agreement'),
            'agreement_introduction' => (string) Config::get('panels.agreements.introduction', ''),
            'standard_terms' => (string) Config::get('panels.agreements.standard_terms', ''),
            'mutual_referral_terms' => 'No referral fees are payable by either party.',
            'confidentiality' => true,
            'client_consent_required' => true,
            'reverse_referrals_no_auto_access' => true,
        ];

        if ($member->panel_type === PanelMember::TYPE_BROKER) {
            $baseTerms['broker_clauses'] = [
                'fsp_number' => $member->fsp_number,
                'fsp_status_at_approval' => $member->fsp_status,
                'fsp_must_remain_current' => true,
                'lapse_auto_suspends_portal_access' => true,
                'broker_responsible_for_regulated_advice' => true,
                'client_consent_required_before_broker_referral' => true,
                'admin_terms' => (string) Config::get('panels.agreements.broker_terms', ''),
            ];
        }

        if ($member->panel_type === PanelMember::TYPE_COACH) {
            $baseTerms['coach_clauses'] = [
                'specialisations' => $member->coach_specialisations ?? [],
                'professional_memberships_displayed_where_held' => true,
                'wellbeing_scope_boundary' => 'Coaching support only; no clinical mental-health diagnosis, treatment, crisis support, or regulated health advice.',
                'client_authorisation_required_for_key_staff' => true,
                'entrepreneur_referrals_require_profile_link' => true,
                'admin_terms' => (string) Config::get('panels.agreements.coach_terms', ''),
            ];
        }

        return [
            ...$baseTerms,
            ...$terms,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationReviewPayload(string $decision, User $admin, string $reason): array
    {
        return [
            'decision' => $decision,
            'reason' => $reason,
            'decided_by_user_id' => $admin->getKey(),
            'decided_at' => now()->toIso8601String(),
        ];
    }
}
