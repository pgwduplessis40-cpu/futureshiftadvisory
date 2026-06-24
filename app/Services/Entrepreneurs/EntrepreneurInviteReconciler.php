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
            $profile = EntrepreneurProfile::query()
                ->with('inviteToken')
                ->whereNull('user_id')
                ->where('email', $email)
                ->where('stage', EntrepreneurStage::INVITED->value)
                ->whereHas('inviteToken', fn ($query) => $query
                    ->where('target_user_type', User::TYPE_ENTREPRENEUR)
                    ->whereNull('accepted_at')
                    ->where('expires_at', '>', now()))
                ->lockForUpdate()
                ->first();

            if (! $profile instanceof EntrepreneurProfile) {
                return null;
            }

            $invite = $profile->inviteToken;
            if ($invite instanceof InviteToken && ! $invite->isAccepted()) {
                $invite->markAccepted($user);
            }

            $profile->forceFill([
                'user_id' => $user->getKey(),
                'stage' => EntrepreneurStage::ONBOARDING,
            ])->save();

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
        });
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
        if ($invite instanceof InviteToken && ! $invite->isAccepted() && ! $invite->isExpired()) {
            $invite->markAccepted($user);
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
}
