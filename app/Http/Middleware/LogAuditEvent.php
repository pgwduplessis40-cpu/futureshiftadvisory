<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Audit\AuditWriter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a `route.viewed` audit event for any route this middleware is
 * attached to. Intended for read-tracking on sensitive endpoints where
 * the *fact* of reading matters as much as the data itself (document
 * downloads, T&C view, signed-PDF preview, audit-log views).
 *
 * Usage in a route file:
 *
 *   Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
 *       ->middleware(['auth', 'audit.read:document.downloaded']);
 *
 * The argument is the action name to record. If omitted, the action
 * defaults to `route.viewed` and includes the route name in context.
 *
 * Only successful (2xx) responses are recorded - a 403/404 means the
 * read did not actually happen.
 *
 * @see PLAN.md section 7.3
 */
final class LogAuditEvent
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function handle(Request $request, Closure $next, ?string $action = null): Response
    {
        $response = $next($request);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return $response;
        }

        $this->audit->recordRead(
            action: $action ?? 'route.viewed',
            subject: null,
            context: [
                'route' => optional($request->route())->getName(),
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $status,
            ],
        );

        return $response;
    }
}
