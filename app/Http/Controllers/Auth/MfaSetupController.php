<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use App\Services\Security\MfaChallenger;
use App\Services\Terms\TermsAcceptanceGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Throwable;

final class MfaSetupController extends Controller
{
    public function __construct(
        private readonly MfaChallenger $mfa,
        private readonly EntrepreneurInviteReconciler $entrepreneurInvites,
        private readonly TermsAcceptanceGate $terms,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->entrepreneurInvites->reconcile($user);
        $this->clearInvalidPendingTwoFactorSetup($user);

        if ($this->mfa->hasCompletedEnrolment($user)) {
            $inviteFlow = $request->session()->pull('fsa.invite_flow', false);

            if ($inviteFlow && ($this->terms->requiresAcceptance($user) || $this->terms->hasDeclinedTermsSuspension($user))) {
                return redirect()->route('terms.pending');
            }

            return redirect()->route('dashboard');
        }

        $request->session()->put('auth.password_confirmed_at', now()->getTimestamp());

        return Inertia::render('auth/mfa-setup', [
            'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
            'hasPendingTwoFactorSetup' => $user->two_factor_secret !== null && ! $user->hasEnabledTwoFactorAuthentication(),
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            'securityUrl' => route('security.edit'),
            'enableUrl' => route('two-factor.enable'),
            'confirmUrl' => route('two-factor.confirm'),
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
        ]);
    }

    private function clearInvalidPendingTwoFactorSetup(User $user): void
    {
        if (
            $user->two_factor_secret === null
            || $user->two_factor_confirmed_at !== null
            || $user->mfa_enabled_at !== null
        ) {
            return;
        }

        try {
            Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

            if ($user->two_factor_recovery_codes !== null) {
                Fortify::currentEncrypter()->decrypt($user->two_factor_recovery_codes);
            }
        } catch (Throwable) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'mfa_enabled_at' => null,
                'mfa_method' => null,
            ])->save();
        }
    }
}
