<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Security\MfaChallenger;
use App\Services\Security\StepUpEvaluator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnforceSessionSecurity
{
    public function __construct(
        private readonly StepUpEvaluator $stepUp,
        private readonly MfaChallenger $mfa,
        private readonly AuditWriter $auditWriter,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        if ($this->stepUp->sessionExpired($request, $user)) {
            $this->auditWriter->record('security.session_expired', actor: $user, context: [
                'timeout_minutes' => $this->stepUp->timeoutMinutes($user),
            ]);

            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->guest(route('login'));
        }

        if (! $request->routeIs('mfa.*', 'logout', 'two-factor.*') && ! $this->mfa->stepUpRequired($request)) {
            $assessment = $this->stepUp->evaluate($request, $user);
            $requiresStepUp = $assessment->requiresStepUp();
            $this->stepUp->syncSessionRecord($request, $assessment, $requiresStepUp);

            if ($requiresStepUp) {
                $this->mfa->requireStepUp($request, $assessment);
                $this->auditWriter->record('security.step_up_required', actor: $user, context: [
                    'risk_score' => $assessment->score,
                    'threshold' => $assessment->threshold,
                    'signals' => $assessment->signals,
                ]);

                return redirect()->guest(route('mfa.challenge', ['reason' => 'step_up']));
            }
        }

        $response = $next($request);

        $this->stepUp->touchSession($request);

        return $response;
    }
}
