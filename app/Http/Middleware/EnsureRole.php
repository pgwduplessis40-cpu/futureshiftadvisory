<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $requiredRoles = $this->normalize($roles);

        if ($requiredRoles === []) {
            return $next($request);
        }

        if ($user->hasAnyRole($requiredRoles)) {
            return $next($request);
        }

        if (in_array($user->fsaRole(), $requiredRoles, true)) {
            return $next($request);
        }

        abort(403);
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function normalize(array $values): array
    {
        return array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            preg_split('/[|,]/', implode(',', $values)) ?: [],
        )));
    }
}
