<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\EntrepreneurProfile;
use App\Models\InviteToken;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EntrepreneurInviteReconciler
{
    public function __construct(private readonly AuditWriter $auditWriter) {}

    public function reconcile(User $user): ?EntrepreneurProfile
    {
        if ($user->user_type !== User::TYPE_ENTREPRENEUR) {
            return null;
        }

        $existing = $user->entrepreneurProfile()->with('inviteToken')->first();
        if ($existing instanceof EntrepreneurProfile) {
            return $this->ensureOnboardingStage($existing, $user);
        }

        $email = Str::lower(trim((string) $user->email));
        if ($email === '') {
            return null;
        }

        return DB::transaction(function () use ($email, $user): ?EntrepreneurProfile {
            $acceptedInvite = InviteToken::query()
                ->whereRaw('lower(trim(email)) = ?', [$email])
                ->where('target_user_type', User::TYPE_ENTREPRENEUR)
                ->whereNotNull('accepted_at')
                ->where(function ($query) use ($user): void {
                    $query
                        ->where('accepted_by_user_id', $user->getKey())
                        ->orWhereNull('accepted_by_user_id');
                })
                ->latest('accepted_at')
                ->latest()
                ->first();

            $profile = EntrepreneurProfile::query()
                ->with('inviteToken')
                ->whereRaw('lower(trim(email)) = ?', [$email])
                ->lockForUpdate()
                ->first();

            if (! $profile instanceof EntrepreneurProfile) {
                $profile = $this->createMissingProfile($user, $acceptedInvite ?? $this->latestInviteFor($email));
            }

            if (! $profile instanceof EntrepreneurProfile) {
                return null;
            }

            $invite = $profile->inviteToken;
            if ($acceptedInvite instanceof InviteToken && (string) $invite?->getKey() !== (string) $acceptedInvite->getKey()) {
                $profile->forceFill(['invite_token_id' => $acceptedInvite->getKey()]);
                $invite = $acceptedInvite;
            }

            if ($invite instanceof InviteToken) {
                $this->ensureInviteAcceptedByUser($invite, $user);
            }

            $updates = [
                'user_id' => $user->getKey(),
            ];
            if (in_array($profile->stage, [EntrepreneurStage::INVITED, EntrepreneurStage::CANCELLED], true)) {
                $updates['stage'] = EntrepreneurStage::ONBOARDING;
            }

            $profile->forceFill($updates)->save();

            $this->auditWriter->record(
                action: 'entrepreneur.onboarding_started',
                subject: $profile,
                actor: $user,
                after: [
                    'entrepreneur_profile_id' => $profile->getKey(),
                    'stage' => $profile->stage instanceof EntrepreneurStage
                        ? $profile->stage->value
                        : (string) $profile->stage,
                    'user_id' => $user->getKey(),
                    'reconciled_from_login' => true,
                ],
            );

            return $profile->refresh()->load('inviteToken');
        });
    }

    private function latestInviteFor(string $email): ?InviteToken
    {
        return InviteToken::query()
            ->whereRaw('lower(trim(email)) = ?', [$email])
            ->where('target_user_type', User::TYPE_ENTREPRENEUR)
            ->latest('accepted_at')
            ->latest('expires_at')
            ->latest()
            ->first();
    }

    private function createMissingProfile(User $user, ?InviteToken $invite): ?EntrepreneurProfile
    {
        $advisorId = $this->advisorIdFor($invite);
        if ($advisorId === null) {
            return null;
        }

        return EntrepreneurProfile::query()->create([
            'user_id' => $user->getKey(),
            'assigned_advisor_id' => $advisorId,
            'invite_token_id' => $invite?->getKey(),
            'name' => $user->name ?: $user->email,
            'email' => Str::lower(trim((string) $user->email)),
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => null,
        ]);
    }

    private function advisorIdFor(?InviteToken $invite): mixed
    {
        if ($invite instanceof InviteToken && $invite->issued_by_user_id !== null) {
            $issuer = User::query()->find($invite->issued_by_user_id);
            if ($issuer instanceof User && in_array($issuer->user_type, [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN], true)) {
                return $issuer->getKey();
            }
        }

        return User::query()
            ->whereIn('user_type', [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN])
            ->oldest()
            ->value('id');
    }

    private function ensureOnboardingStage(EntrepreneurProfile $profile, User $user): EntrepreneurProfile
    {
        if ($profile->stage !== EntrepreneurStage::INVITED) {
            return $profile;
        }

        $profile->forceFill([
            'stage' => EntrepreneurStage::ONBOARDING,
        ])->save();

        $invite = $profile->inviteToken;
        if ($invite instanceof InviteToken) {
            $this->ensureInviteAcceptedByUser($invite, $user);
        }

        $this->auditWriter->record(
            action: 'entrepreneur.onboarding_started',
            subject: $profile,
            actor: $user,
            after: [
                'entrepreneur_profile_id' => $profile->getKey(),
                'stage' => EntrepreneurStage::ONBOARDING->value,
                'user_id' => $user->getKey(),
                'reconciled_from_login' => true,
            ],
        );

        return $profile->refresh()->load('inviteToken');
    }

    private function ensureInviteAcceptedByUser(InviteToken $invite, User $user): void
    {
        if (! $invite->isAccepted()) {
            if (! $invite->isExpired()) {
                $invite->markAccepted($user);
            }

            return;
        }

        if ($invite->accepted_by_user_id === null) {
            $invite->forceFill([
                'accepted_by_user_id' => $user->getKey(),
            ])->save();
        }
    }
}
