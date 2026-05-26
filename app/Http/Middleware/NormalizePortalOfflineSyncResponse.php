<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Portal\PortalOfflineSync;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NormalizePortalOfflineSyncResponse
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->headers->has(PortalOfflineSync::SYNC_HEADER) || ! $response instanceof RedirectResponse) {
            return $response;
        }

        $location = $response->headers->get('Location');
        if (! is_string($location) || ! $this->isAuthFlowRedirect($location)) {
            return $response;
        }

        return response()->json([
            'message' => 'Portal offline sync requires an active authenticated session.',
        ], 401);
    }

    private function isAuthFlowRedirect(string $location): bool
    {
        $path = parse_url($location, PHP_URL_PATH);
        $path = '/'.ltrim(is_string($path) ? $path : $location, '/');

        return $path === '/login'
            || str_starts_with($path, '/mfa/')
            || $path === '/mfa'
            || str_starts_with($path, '/terms')
            || str_starts_with($path, '/email/verify')
            || str_starts_with($path, '/verify-email')
            || str_starts_with($path, '/verification');
    }
}
