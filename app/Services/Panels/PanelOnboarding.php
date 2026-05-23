<?php

declare(strict_types=1);

namespace App\Services\Panels;

use App\Models\PanelAgreement;
use App\Models\PanelMember;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Pdf\PdfRenderer;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PanelOnboarding
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly KeyEnvelope $envelope,
        private readonly AuditWriter $audit,
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

        $member = PanelMember::query()->updateOrCreate(
            ['user_id' => $user->getKey(), 'panel_type' => $panelType],
            [
                'status' => PanelMember::STATUS_APPLICATION_PENDING,
                'application' => $application,
                'applied_at' => now(),
            ],
        );

        $this->audit->record('panel.application_submitted', subject: $member, actor: $user, after: [
            'panel_type' => $panelType,
            'status' => PanelMember::STATUS_APPLICATION_PENDING,
        ]);

        return $member->refresh();
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

    public function signAgreement(PanelAgreement $agreement, User $actor): PanelAgreement
    {
        return DB::transaction(function () use ($agreement, $actor): PanelAgreement {
            $agreement = $agreement->refresh()->loadMissing('panelMember.user');
            $member = $agreement->panelMember;

            if ((string) $member->user_id !== (string) $actor->getKey()) {
                throw new PanelAccessException('Only the panel member can sign their panel agreement.');
            }

            $pdf = $this->renderer->render($this->agreementHtml($agreement, $actor));
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
                'signed_at' => now(),
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
        return [
            'panel_type' => $member->panel_type,
            'mutual_referral_terms' => 'No referral fees are payable by either party.',
            'confidentiality' => true,
            'client_consent_required' => true,
            'reverse_referrals_no_auto_access' => true,
            ...$terms,
        ];
    }

    private function agreementHtml(PanelAgreement $agreement, User $actor): string
    {
        $terms = collect($agreement->terms ?? [])
            ->map(fn (mixed $value, string $key): string => '<p><strong>'.$this->escape($key).'</strong>: '.$this->escape(is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR)).'</p>')
            ->implode('');

        return '<!doctype html><html><body><h1>Future Shift Advisory panel agreement</h1><p>Signed by '.$this->escape($actor->name).'</p>'.$terms.'</body></html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
