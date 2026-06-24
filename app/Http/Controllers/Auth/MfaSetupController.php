<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use App\Services\Security\MfaChallenger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

final class MfaSetupController extends Controller
{
    public function __construct(
        private readonly MfaChallenger $mfa,
        private readonly EntrepreneurInviteReconciler $entrepreneurInvites,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->entrepreneurInvites->reconcile($user);

        if ($this->mfa->hasCompletedEnrolment($user)) {
            return redirect()->route($request->session()->pull('fsa.invite_flow', false)
                ? 'terms.pending'
                : 'dashboard');
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
}
