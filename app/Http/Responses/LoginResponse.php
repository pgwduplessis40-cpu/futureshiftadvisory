<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use App\Services\Security\MfaChallenger;
use App\Services\Security\TwoFactorStateSanitizer;
use App\Support\RequestContext;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

final class LoginResponse implements LoginResponseContract
{
    public function __construct(
        private readonly MfaChallenger $mfa,
        private readonly EntrepreneurInviteReconciler $entrepreneurInvites,
        private readonly TwoFactorStateSanitizer $twoFactorState,
        private readonly RequestContext $requestContext,
    ) {}

    public function toResponse($request)
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->requestContext->withSystemContext(function () use ($user): void {
                $this->entrepreneurInvites->reconcile($user);
                $this->twoFactorState->sanitize($user);
            });
        }

        if (
            (bool) config('security.mfa_required', true)
            && $user instanceof User
            && ! $this->mfa->hasCompletedEnrolment($user)
        ) {
            if ($request->hasSession()) {
                $request->session()->put('auth.password_confirmed_at', now()->getTimestamp());
            }

            return $request->wantsJson()
                ? new JsonResponse(['two_factor_setup' => true], 409)
                : redirect()->route('mfa.setup');
        }

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(config('fortify.home'));
    }
}
