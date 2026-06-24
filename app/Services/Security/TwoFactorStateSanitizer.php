<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\User;
use Laravel\Fortify\Fortify;
use Throwable;

final class TwoFactorStateSanitizer
{
    public function sanitize(User $user): bool
    {
        if ($user->two_factor_secret === null) {
            if (
                $user->two_factor_recovery_codes === null
                && $user->two_factor_confirmed_at === null
                && $user->mfa_enabled_at === null
                && $user->mfa_method === null
            ) {
                return false;
            }

            $this->reset($user);

            return true;
        }

        try {
            Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
        } catch (Throwable) {
            $this->reset($user);

            return true;
        }

        if ($user->two_factor_confirmed_at !== null || $user->mfa_enabled_at !== null) {
            return false;
        }

        try {
            if ($user->two_factor_recovery_codes !== null) {
                Fortify::currentEncrypter()->decrypt($user->two_factor_recovery_codes);
            }
        } catch (Throwable) {
            $this->reset($user);

            return true;
        }

        return false;
    }

    private function reset(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'mfa_enabled_at' => null,
            'mfa_method' => null,
        ])->save();
    }
}
