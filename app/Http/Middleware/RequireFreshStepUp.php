<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Security\MfaChallenger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

final class RequireFreshStepUp
{
    public function __construct(
        private readonly MfaChallenger $mfa,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('security.mfa_required', true)) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $this->mfa->hasCompletedEnrolment($user)) {
            return redirect()->route('mfa.setup');
        }

        if ($this->hasFreshChallenge($request, $user)) {
            return $next($request);
        }

        $this->mfa->requireFreshStepUp($request, 'fresh_step_up');
        $this->audit->record('security.fresh_step_up_required', actor: $user, context: [
            'path' => $request->path(),
            'fresh_minutes' => $this->freshMinutes(),
        ]);

        return redirect()->guest(route('mfa.challenge', ['reason' => 'fresh_step_up']));
    }

    private function hasFreshChallenge(Request $request, User $user): bool
    {
        if (! $this->mfa->sessionIsVerified($request, $user)) {
            return false;
        }

        $confirmedAt = $request->session()->get(MfaChallenger::SESSION_CONFIRMED_AT);
        if (! is_numeric($confirmedAt)) {
            return false;
        }

        return Date::createFromTimestamp((int) $confirmedAt)
            ->addMinutes($this->freshMinutes())
            ->isFuture();
    }

    private function freshMinutes(): int
    {
        return max(1, (int) config('security.fresh_step_up_minutes', 5));
    }
}
