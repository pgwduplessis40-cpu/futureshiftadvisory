<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permanently redirects the www host to the canonical apex domain, so search
 * engines index a single host and www never serves duplicate pages. Runs ahead
 * of session and auth middleware, so a www request never starts a session.
 */
final class RedirectWwwToApex
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (str_starts_with($host, 'www.')) {
            return redirect()->away(
                $request->getScheme().'://'.substr($host, 4).$request->getRequestUri(),
                301,
            );
        }

        return $next($request);
    }
}
