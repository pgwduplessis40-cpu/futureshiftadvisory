<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\MfaChallenger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class MfaSetupController extends Controller
{
    public function __construct(private readonly MfaChallenger $mfa) {}

    public function show(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->mfa->hasCompletedEnrolment($user)) {
            return redirect()->route($request->session()->pull('fsa.invite_flow', false)
                ? 'terms.pending'
                : 'dashboard');
        }

        return Inertia::render('auth/mfa-setup', [
            'requiresConfirmation' => true,
            'securityUrl' => route('security.edit'),
            'enableUrl' => route('two-factor.enable'),
            'confirmUrl' => route('two-factor.confirm'),
        ]);
    }
}
