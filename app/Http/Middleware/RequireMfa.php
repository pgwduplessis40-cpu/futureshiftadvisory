<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Security\MfaChallenger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireMfa
{
    public function __construct(private readonly MfaChallenger $mfa) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('security.mfa_required', true)) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        if ($this->isExemptRoute($request)) {
            return $next($request);
        }

        if (! $this->mfa->hasCompletedEnrolment($user)) {
            return redirect()->route('mfa.setup');
        }

        if (! $this->mfa->sessionIsVerified($request, $user)) {
            return redirect()->guest(route('mfa.challenge'));
        }

        return $next($request);
    }

    private function isExemptRoute(Request $request): bool
    {
        return $request->routeIs(
            'logout',
            'mfa.*',
            'security.edit',
            'two-factor.*',
            'password.*',
            'verification.*',
        );
    }
}
