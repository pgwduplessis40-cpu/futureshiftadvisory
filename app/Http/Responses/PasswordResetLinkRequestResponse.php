<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

final class PasswordResetLinkRequestResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    private const MESSAGE = 'If that email has an account, a reset link is on its way.';

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => self::MESSAGE,
            ]);
        }

        return back()->with('status', self::MESSAGE);
    }
}
