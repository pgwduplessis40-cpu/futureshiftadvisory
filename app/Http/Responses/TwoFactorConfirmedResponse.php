<?php

declare(strict_types=1);

namespace App\Http\Responses;

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
            return redirect()->route('terms.pending');
        }

        return back()->with('status', Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED);
    }
}
