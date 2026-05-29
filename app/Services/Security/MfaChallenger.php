<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\MfaFactor;
use App\Models\User;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Throwable;

final class MfaChallenger
{
    public const SESSION_CONFIRMED_AT = 'auth.mfa_confirmed_at';

    public const SESSION_USER_ID = 'auth.mfa_user_id';

    public const SESSION_STEP_UP_REQUIRED = 'auth.step_up_required';

    public const SESSION_STEP_UP_REASON = 'auth.step_up_reason';

    public const SESSION_STEP_UP_SCORE = 'auth.step_up_score';

    private const SESSION_FAILED_ATTEMPTS = 'auth.mfa_failed_attempts';

    private const SESSION_LOCKED_UNTIL = 'auth.mfa_locked_until';

    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
        private readonly KeyEnvelope $envelope,
    ) {}

    public function hasCompletedEnrolment(User $user): bool
    {
        return $user->mfa_enabled_at !== null
            && method_exists($user, 'hasEnabledTwoFactorAuthentication')
            && $user->hasEnabledTwoFactorAuthentication();
    }

    public function sessionIsVerified(Request $request, User $user): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        return (string) $request->session()->get(self::SESSION_USER_ID) === (string) $user->getAuthIdentifier()
            && is_numeric($request->session()->get(self::SESSION_CONFIRMED_AT))
            && ! $this->stepUpRequired($request);
    }

    public function markConfirmed(User $user, Request $request): void
    {
        $user->forceFill([
            'mfa_enabled_at' => $user->mfa_enabled_at ?? now(),
            'mfa_method' => User::MFA_METHOD_TOTP,
        ])->save();

        $this->syncTotpFactor($user, confirmed: true);
        $this->markChallengePassed($request, $user);
    }

    public function markChallengePassed(Request $request, User $user): void
    {
        if ($request->hasSession()) {
            $request->session()->put([
                self::SESSION_CONFIRMED_AT => Date::now()->getTimestamp(),
                self::SESSION_USER_ID => (string) $user->getAuthIdentifier(),
            ]);
            $request->session()->forget([
                self::SESSION_FAILED_ATTEMPTS,
                self::SESSION_LOCKED_UNTIL,
                self::SESSION_STEP_UP_REQUIRED,
                self::SESSION_STEP_UP_REASON,
                self::SESSION_STEP_UP_SCORE,
            ]);
        }

        MfaFactor::query()
            ->where('user_id', $user->getKey())
            ->where('type', MfaFactor::TYPE_TOTP)
            ->update(['last_used_at' => now()]);
    }

    public function verify(Request $request, User $user): void
    {
        if (! $this->hasCompletedEnrolment($user)) {
            throw ValidationException::withMessages(['code' => 'Two-factor authentication is not enrolled for this account.']);
        }

        $this->assertNotLocked($request);

        $code = (string) $request->input('code', '');
        $recoveryCode = (string) $request->input('recovery_code', '');

        if ($code !== '' && $this->verifyCode($user, $code)) {
            $this->markChallengePassed($request, $user);

            return;
        }

        if ($recoveryCode !== '' && $this->verifyRecoveryCode($user, $recoveryCode)) {
            $this->markChallengePassed($request, $user);

            return;
        }

        $this->recordFailedAttempt($request);

        throw ValidationException::withMessages(['code' => 'The provided two-factor authentication code was invalid.']);
    }

    public function requireStepUp(Request $request, StepUpAssessment $assessment): void
    {
        if ($request->hasSession()) {
            $request->session()->put([
                self::SESSION_STEP_UP_REQUIRED => true,
                self::SESSION_STEP_UP_REASON => 'step_up',
                self::SESSION_STEP_UP_SCORE => $assessment->score,
            ]);
        }
    }

    public function stepUpRequired(Request $request): bool
    {
        return $request->hasSession() && $request->session()->get(self::SESSION_STEP_UP_REQUIRED) === true;
    }

    private function verifyCode(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        try {
            return $this->provider->verify(
                Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                $code,
            );
        } catch (Throwable) {
            return false;
        }
    }

    private function assertNotLocked(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $lockedUntil = $request->session()->get(self::SESSION_LOCKED_UNTIL);
        if (! is_numeric($lockedUntil)) {
            return;
        }

        $lockedUntil = (int) $lockedUntil;
        if ($lockedUntil <= Date::now()->getTimestamp()) {
            $request->session()->forget([self::SESSION_FAILED_ATTEMPTS, self::SESSION_LOCKED_UNTIL]);

            return;
        }

        $minutes = max(1, (int) ceil(($lockedUntil - Date::now()->getTimestamp()) / 60));

        throw ValidationException::withMessages([
            'code' => "Too many incorrect authentication attempts. Try again in {$minutes} minute".($minutes === 1 ? '' : 's').'.',
        ]);
    }

    private function recordFailedAttempt(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $attempts = ((int) $request->session()->get(self::SESSION_FAILED_ATTEMPTS, 0)) + 1;
        $limit = max(1, (int) config('security.mfa_failed_attempt_limit', 3));

        if ($attempts < $limit) {
            $request->session()->put(self::SESSION_FAILED_ATTEMPTS, $attempts);

            return;
        }

        $request->session()->put([
            self::SESSION_FAILED_ATTEMPTS => $attempts,
            self::SESSION_LOCKED_UNTIL => Date::now()
                ->addMinutes(max(1, (int) config('security.mfa_lockout_minutes', 15)))
                ->getTimestamp(),
        ]);
    }

    private function verifyRecoveryCode(User $user, string $recoveryCode): bool
    {
        $codes = $user->recoveryCodes();
        if (! in_array($recoveryCode, $codes, true)) {
            return false;
        }

        $user->replaceRecoveryCode($recoveryCode);

        return true;
    }

    private function syncTotpFactor(User $user, bool $confirmed): void
    {
        MfaFactor::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'type' => MfaFactor::TYPE_TOTP,
            ],
            [
                'label' => 'Authenticator app',
                'secret_envelope' => $user->two_factor_secret === null
                    ? null
                    : $this->envelope->encrypt(Fortify::currentEncrypter()->decrypt($user->two_factor_secret)),
                'recovery_codes_envelope' => $user->two_factor_recovery_codes === null
                    ? null
                    : $this->envelope->encrypt(Fortify::currentEncrypter()->decrypt($user->two_factor_recovery_codes)),
                'confirmed_at' => $confirmed ? now() : null,
            ],
        );
    }
}
