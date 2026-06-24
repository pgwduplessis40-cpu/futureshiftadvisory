<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\User;
use App\Services\Terms\TermsAcceptanceGate;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorConfirmedResponse as TwoFactorConfirmedResponseContract;
use Laravel\Fortify\Fortify;

final class TwoFactorConfirmedResponse implements TwoFactorConfirmedResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 200);
        }

        if ($request->session()->pull('fsa.invite_flow', false)) {
            $user = $request->user();
            $terms = app(TermsAcceptanceGate::class);

            if ($user instanceof User && ($terms->requiresAcceptance($user) || $terms->hasDeclinedTermsSuspension($user))) {
                return redirect()->route('terms.pending');
            }

            return redirect()->route('dashboard');
        }

        return back()->with('status', Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED);
    }
}
