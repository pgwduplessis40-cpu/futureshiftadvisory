<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Security\MfaChallenger;
use App\Services\Terms\TermsAcceptanceGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireAcceptedTerms
{
    public function __construct(
        private readonly TermsAcceptanceGate $gate,
        private readonly MfaChallenger $mfa,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || $this->isExemptRoute($request)) {
            return $next($request);
        }

        if (! $this->mfa->hasCompletedEnrolment($user) || ! $this->mfa->sessionIsVerified($request, $user)) {
            return $next($request);
        }

        if ($this->gate->hasDeclinedTermsSuspension($user)) {
            return redirect()->route('terms.declined');
        }

        if ($this->gate->requiresAcceptance($user)) {
            return redirect()->guest(route('terms.pending'));
        }

        return $next($request);
    }

    private function isExemptRoute(Request $request): bool
    {
        return $request->routeIs(
            'logout',
            'mfa.*',
            'terms.*',
            'two-factor.*',
            'verification.*',
        );
    }
}
