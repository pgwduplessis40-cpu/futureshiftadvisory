<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that pushes the authenticated user's FSA role and accessible
 * client UUIDs into the current Postgres session, so row-level security
 * policies on per-client tables enforce isolation at the database layer.
 *
 * Runs on every web and api request. For unauthenticated requests it
 * still applies an explicit "guest" context, which means RLS-protected
 * tables return zero rows by default - no row is ever leaked because
 * the middleware forgot to set context.
 *
 * @see PLAN.md section 6.2 - row-level security policy template
 * @see PLAN.md section 7.4 - integration scaffolding pattern
 * @see docs/architecture/postgres-rls.md
 */
final class EnforceClientScope
{
    public function __construct(private readonly RequestContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $role = $this->context->resolveRole($user);
        $userId = $user?->getAuthIdentifier();
        $userId = is_scalar($userId) ? (string) $userId : null;

        $this->context->apply($role, [], $userId);
        $clientIds = $this->context->resolveClientIds($user);

        $this->context->apply($role, $clientIds, $userId);

        return $next($request);
    }
}
