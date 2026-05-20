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

final class MfaChallengeController extends Controller
{
    public function __construct(private readonly MfaChallenger $mfa) {}

    public function show(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->mfa->hasCompletedEnrolment($user)) {
            return redirect()->route('mfa.setup');
        }

        if ($this->mfa->sessionIsVerified($request, $user)) {
            return redirect()->intended(route('dashboard'));
        }

        return Inertia::render('auth/mfa-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $this->mfa->verify($request, $user);

        return redirect()->intended(route('dashboard'));
    }
}
