<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use App\Services\Security\MfaChallenger;
use App\Services\Security\TwoFactorStateSanitizer;
use App\Services\Terms\TermsAcceptanceGate;
use App\Support\RequestContext;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

final class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function __construct(
        private readonly MfaChallenger $mfa,
        private readonly EntrepreneurInviteReconciler $entrepreneurInvites,
        private readonly TwoFactorStateSanitizer $twoFactorState,
        private readonly TermsAcceptanceGate $termsGate,
        private readonly RequestContext $requestContext,
    ) {}

    public function toResponse($request)
    {
        $user = $request->user();
        $termsRequired = false;

        if ($user instanceof User) {
            $termsRequired = $this->requestContext->withSystemContext(function () use ($request, $user): bool {
                $this->entrepreneurInvites->reconcile($user);
                $this->twoFactorState->sanitize($user);
                $this->mfa->markChallengePassed($request, $user);

                return $this->termsGate->requiresAcceptance($user)
                    || $this->termsGate->hasDeclinedTermsSuspension($user);
            });

            if ($termsRequired) {
                return $request->wantsJson()
                    ? new JsonResponse(['terms_required' => true], 409)
                    : redirect()->route('terms.pending');
            }
        }

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(config('fortify.home'));
    }
}
