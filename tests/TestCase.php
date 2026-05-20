<?php

namespace Tests;

use App\Models\User;
use App\Services\Security\MfaChallenger;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    protected function actingAsMfa(User $user): static
    {
        $user->forceFill([
            'two_factor_secret' => $user->two_factor_secret ?? encrypt('secret'),
            'two_factor_recovery_codes' => $user->two_factor_recovery_codes ?? encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => $user->two_factor_confirmed_at ?? now(),
            'mfa_enabled_at' => $user->mfa_enabled_at ?? now(),
            'mfa_method' => $user->mfa_method ?? User::MFA_METHOD_TOTP,
        ])->save();

        $this->actingAs($user);

        return $this->withSession([
            MfaChallenger::SESSION_CONFIRMED_AT => now()->getTimestamp(),
            MfaChallenger::SESSION_USER_ID => (string) $user->getAuthIdentifier(),
        ]);
    }
}
