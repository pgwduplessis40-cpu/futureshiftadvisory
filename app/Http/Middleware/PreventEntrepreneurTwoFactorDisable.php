<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PreventEntrepreneurTwoFactorDisable
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(
            $request->isMethod('delete')
                && $request->routeIs('two-factor.disable')
                && $user instanceof User
                && $user->user_type === User::TYPE_ENTREPRENEUR,
            403,
            'Entrepreneur accounts must keep two-factor authentication enabled.',
        );

        return $next($request);
    }
}
