<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $requiredPermissions = $this->normalize($permissions);

        if ($requiredPermissions === []) {
            return $next($request);
        }

        foreach ($requiredPermissions as $permission) {
            if ($this->configuredRoleGrants($user, $permission) || $user->can($permission)) {
                return $next($request);
            }
        }

        abort(403);
    }

    private function configuredRoleGrants(User $user, string $permission): bool
    {
        foreach ([$user->primary_role, $user->user_type] as $role) {
            if (is_string($role) && in_array($permission, Permission::valuesForRole($role), true)) {
                return true;
            }
        }

        return false;
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
