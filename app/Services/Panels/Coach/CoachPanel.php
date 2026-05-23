<?php

declare(strict_types=1);

namespace App\Services\Panels\Coach;

use App\Enums\CoachSpecialisation;
use App\Models\Client;
use App\Models\CoachReferralAuthorisation;
use App\Models\EntrepreneurProfile;
use App\Models\PanelMember;
use App\Models\Referral;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Panels\PanelAccessException;
use App\Services\Panels\ReferralLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CoachPanel
{
    public const SUBJECT_OWNER = 'owner';

    public const SUBJECT_KEY_STAFF = 'key_staff';

    public const SUBJECT_ENTREPRENEUR = 'entrepreneur';

    public function __construct(
        private readonly ReferralLifecycle $referrals,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<int, string>  $specialisations
     * @param  array<string, mixed>  $profile
     * @param  array<int, array<string, mixed>>  $memberships
     * @param  array<string, mixed>  $vetting
     */
    public function vet(PanelMember $member, User $admin, array $specialisations, array $profile = [], array $memberships = [], array $vetting = []): PanelMember
    {
        if ($member->panel_type !== PanelMember::TYPE_COACH) {
            throw new InvalidArgumentException('Coach vetting only applies to coach panel members.');
        }

        if (! in_array($admin->user_type, [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN], true)) {
            throw new InvalidArgumentException('Only advisors or super admins can vet coach panel members.');
        }

        $specialisations = $this->normaliseSpecialisations($specialisations);

        if ($specialisations === []) {
            throw new InvalidArgumentException('At least one coach specialisation is required.');
        }

        $member->forceFill([
            'coach_specialisations' => $specialisations,
            'coach_profile' => $profile,
            'professional_memberships' => $memberships,
            'coach_vetting' => [
                'admin_managed' => true,
                ...$vetting,
            ],
            'coach_vetted_by_user_id' => $admin->getKey(),
            'coach_vetted_at' => now(),
        ])->save();

        $this->audit->record('panel.coach_vetted', subject: $member, actor: $admin, after: [
            'specialisations' => $specialisations,
            'professional_memberships_count' => count($memberships),
            'admin_managed' => true,
        ]);

        return $member->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function authoriseKeyStaff(Client $client, User $authoriser, string $staffName, ?string $staffEmail = null, ?string $purpose = null, array $payload = []): CoachReferralAuthorisation
    {
        $authorisation = CoachReferralAuthorisation::query()->create([
            'client_id' => $client->getKey(),
            'authorised_by_user_id' => $authoriser->getKey(),
            'staff_name' => $staffName,
            'staff_email' => $staffEmail === null ? null : Str::lower($staffEmail),
            'purpose' => $purpose,
            'payload' => $payload,
            'granted_at' => now(),
        ]);

        $this->audit->record('coach.key_staff_authorised', subject: $authorisation, actor: $authoriser, after: [
            'client_id' => $client->getKey(),
            'staff_name' => $staffName,
        ]);

        return $authorisation->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createReferral(
        Client $client,
        PanelMember $coach,
        User $advisor,
        string $specialisation,
        string $subjectType,
        array $payload = [],
        ?CoachReferralAuthorisation $authorisation = null,
    ): Referral {
        $coach = $this->assertCoach($coach, $specialisation);
        $subjectType = $this->assertClientSubject($subjectType, $client, $authorisation);

        return DB::transaction(function () use ($client, $coach, $advisor, $specialisation, $subjectType, $payload, $authorisation): Referral {
            $referral = $this->referrals->create($client, $coach, $advisor, [
                ...$payload,
                'coach_specialisation' => $specialisation,
                'referred_subject_type' => $subjectType,
                'coach_referral_authorisation_id' => $authorisation?->getKey(),
            ]);

            $referral->forceFill([
                'coach_specialisation' => $specialisation,
                'referred_subject_type' => $subjectType,
                'coach_referral_authorisation_id' => $authorisation?->getKey(),
            ])->save();

            $this->audit->record('coach.referral_classified', subject: $referral, actor: $advisor, after: [
                'coach_specialisation' => $specialisation,
                'referred_subject_type' => $subjectType,
                'coach_referral_authorisation_id' => $authorisation?->getKey(),
            ]);

            return $referral->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createEntrepreneurReferral(
        EntrepreneurProfile $entrepreneur,
        PanelMember $coach,
        User $advisor,
        string $specialisation,
        array $payload = [],
    ): Referral {
        $coach = $this->assertCoach($coach, $specialisation);

        return DB::transaction(function () use ($entrepreneur, $coach, $advisor, $specialisation, $payload): Referral {
            $referral = $this->referrals->createForEntrepreneur($entrepreneur, $coach, $advisor, [
                ...$payload,
                'coach_specialisation' => $specialisation,
                'referred_subject_type' => self::SUBJECT_ENTREPRENEUR,
            ]);

            $referral->forceFill([
                'coach_specialisation' => $specialisation,
                'referred_subject_type' => self::SUBJECT_ENTREPRENEUR,
            ])->save();

            $this->audit->record('coach.referral_classified', subject: $referral, actor: $advisor, after: [
                'coach_specialisation' => $specialisation,
                'referred_subject_type' => self::SUBJECT_ENTREPRENEUR,
                'entrepreneur_profile_id' => $entrepreneur->getKey(),
            ]);

            return $referral->refresh();
        });
    }

    /**
     * @param  array<int, string>  $specialisations
     * @return array<int, string>
     */
    private function normaliseSpecialisations(array $specialisations): array
    {
        $allowed = CoachSpecialisation::values();

        return collect($specialisations)
            ->map(fn (string $specialisation): string => trim($specialisation))
            ->filter()
            ->unique()
            ->each(function (string $specialisation) use ($allowed): void {
                if (! in_array($specialisation, $allowed, true)) {
                    throw new InvalidArgumentException("Unsupported coach specialisation [{$specialisation}].");
                }
            })
            ->values()
            ->all();
    }

    private function assertCoach(PanelMember $coach, string $specialisation): PanelMember
    {
        $coach = $coach->refresh();

        if ($coach->panel_type !== PanelMember::TYPE_COACH || $coach->status !== PanelMember::STATUS_ACTIVE) {
            throw new PanelAccessException('Coach referrals require an active coach panel member.');
        }

        $specialisations = $coach->coach_specialisations ?? [];

        if (! in_array($specialisation, $specialisations, true)) {
            throw new InvalidArgumentException('Coach is not vetted for the requested specialisation.');
        }

        return $coach;
    }

    private function assertClientSubject(string $subjectType, Client $client, ?CoachReferralAuthorisation $authorisation): string
    {
        if (! in_array($subjectType, [self::SUBJECT_OWNER, self::SUBJECT_KEY_STAFF], true)) {
            throw new InvalidArgumentException('Client coach referrals support owner or key staff subjects.');
        }

        if ($subjectType !== self::SUBJECT_KEY_STAFF) {
            return $subjectType;
        }

        if (! $authorisation instanceof CoachReferralAuthorisation) {
            throw new InvalidArgumentException('Key-staff coach referrals require client authorisation.');
        }

        $authorisation = $authorisation->refresh();

        if ((string) $authorisation->client_id !== (string) $client->getKey() || $authorisation->revoked_at !== null) {
            throw new InvalidArgumentException('Key-staff coach referral authorisation is not active for this client.');
        }

        return $subjectType;
    }
}
