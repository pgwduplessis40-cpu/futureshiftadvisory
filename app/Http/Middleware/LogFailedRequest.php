<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Audit\AuditWriter;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class LogFailedRequest
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->recordFailure($request, $this->statusFor($exception), $exception);

            throw $exception;
        }

        $this->recordFailure($request, $response->getStatusCode());

        return $response;
    }

    private function recordFailure(Request $request, int $status, ?Throwable $exception = null): void
    {
        if (! $this->shouldRecord($status)) {
            return;
        }

        $actor = $request->user();
        if (! $actor instanceof User) {
            return;
        }

        try {
            $this->audit->record('http.request_failed', actor: $actor, context: [
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                'status' => $status,
                'exception' => $exception === null ? null : class_basename($exception),
            ]);
        } catch (Throwable) {
            // Audit persistence must never replace the original response.
        }
    }

    private function shouldRecord(int $status): bool
    {
        return in_array($status, [403, 404], true) || $status >= 500;
    }

    private function statusFor(Throwable $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        if ($exception instanceof ModelNotFoundException) {
            return 404;
        }

        if ($exception instanceof AuthorizationException) {
            return 403;
        }

        return 500;
    }
}
