<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\User;
use App\Services\Security\MfaChallenger;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

final class LoginResponse implements LoginResponseContract
{
    public function __construct(private readonly MfaChallenger $mfa) {}

    public function toResponse($request)
    {
        $user = $request->user();

        if (
            (bool) config('security.mfa_required', true)
            && $user instanceof User
            && ! $this->mfa->hasCompletedEnrolment($user)
        ) {
            return $request->wantsJson()
                ? new JsonResponse(['two_factor_setup' => true], 409)
                : redirect()->route('mfa.setup');
        }

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(config('fortify.home'));
    }
}
