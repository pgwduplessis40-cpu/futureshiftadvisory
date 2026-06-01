<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Security\MfaChallenger;
use App\Services\Security\StepUpEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class MfaChallengeController extends Controller
{
    public function __construct(
        private readonly MfaChallenger $mfa,
        private readonly StepUpEvaluator $stepUp,
        private readonly AuditWriter $auditWriter,
    ) {}

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

        return Inertia::render('auth/mfa-challenge', [
            'reason' => $this->mfa->stepUpRequired($request) ? $this->mfa->stepUpReason($request) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $wasStepUp = $this->mfa->stepUpRequired($request);

        try {
            $this->mfa->verify($request, $user);
        } catch (ValidationException $exception) {
            if ($wasStepUp) {
                $this->auditWriter->record('security.step_up_failed', actor: $user, context: [
                    'risk_score' => $request->session()->get(MfaChallenger::SESSION_STEP_UP_SCORE),
                ]);
            }

            throw $exception;
        }

        $this->stepUp->rememberDevice($request);

        return redirect()->intended(route('dashboard'));
    }
}
