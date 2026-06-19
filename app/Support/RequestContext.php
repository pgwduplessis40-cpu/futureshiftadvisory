<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the per-request FSA role and client scope, and pushes them down
 * into the Postgres session so row-level security policies can enforce
 * isolation at the database layer. WO-14 also stores the authenticated
 * user id so the client-team lookup can discover a user's client scope.
 *
 * Phase 1 ships a deliberately minimal user-to-scope resolver: the
 * implementation reads a hard-coded "no clients yet" set until the clients
 * table is created (WO-14). This file is the single place where that
 * resolution lives, so future WOs only need to extend the resolver here.
 *
 * @see PLAN.md section 6.2 - row-level security policy template
 * @see PLAN.md section 7.4 - integration scaffolding pattern
 * @see docs/architecture/postgres-rls.md
 */
final class RequestContext
{
    public const ROLE_GUEST = 'guest';

    public const ROLE_SUPER_ADMIN = 'super_admin';

    /**
     * Resolve the FSA role for the given (possibly null) authenticated user.
     *
     * Phase 1: only the `super_admin` and `guest` distinction is meaningful
     * for RLS scope. Role-based authorization (Spatie roles) lands in WO-07
     * and will extend this method to return the user's primary role string.
     */
    public function resolveRole(?Authenticatable $user): string
    {
        if ($user === null) {
            return self::ROLE_GUEST;
        }

        // Until WO-07 introduces the Spatie role binding, treat any
        // authenticated user as a non-privileged scope. This is the safe
        // default: RLS policies treat any non-super_admin role as
        // "must match an entry in fsa.client_ids".
        return method_exists($user, 'fsaRole') ? (string) $user->fsaRole() : 'authenticated';
    }

    /**
     * Resolve the set of client UUIDs the user may access.
     *
     * Phase 1: returns an empty list until the clients table exists. The
     * resolver is structured so WO-14 (clients) and WO-07 (roles) can fill
     * it without changing any call site.
     *
     * @return array<int, string>
     */
    public function resolveClientIds(?Authenticatable $user): array
    {
        if ($user === null) {
            return [];
        }

        if (method_exists($user, 'accessibleClientIds')) {
            /** @var array<int, string> $ids */
            $ids = (array) $user->accessibleClientIds();

            return array_values(array_map('strval', $ids));
        }

        return [];
    }

    /**
     * Push the resolved role and client scope into the current Postgres
     * session. No-op on non-Postgres connections so the dev/test fallback
     * to other drivers does not blow up.
     *
     * @param  array<int, string>  $clientIds
     *
     * @throws AuthorizationException
     */
    public function apply(
        string $role,
        array $clientIds,
        ?string $userId = null,
        ?string $reportId = null,
        ?string $npoEngagementId = null,
    ): void {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        if ($role === User::TYPE_NPO_BOARD_MEMBER && $npoEngagementId !== null && trim($npoEngagementId) !== '') {
            DB::statement(
                'SELECT fsa_set_request_context(?, ?, ?, ?, ?)',
                [$role, '', $userId, null, null]
            );

            $allowed = DB::selectOne('SELECT fsa_user_is_board_member_of(?::uuid) AS allowed', [$npoEngagementId]);

            if (! (bool) ($allowed->allowed ?? false)) {
                DB::statement(
                    'SELECT fsa_set_request_context(?, ?, ?, ?, ?)',
                    [$role, '', $userId, null, null]
                );

                throw new AuthorizationException('Board member is not assigned to this NPO engagement.');
            }
        } elseif ($role !== User::TYPE_NPO_BOARD_MEMBER) {
            $npoEngagementId = null;
        }

        DB::statement(
            'SELECT fsa_set_request_context(?, ?, ?, ?, ?)',
            [$role, implode(',', $clientIds), $userId, $reportId, $npoEngagementId]
        );
    }

    /**
     * Temporarily switch the current Postgres session to trusted system scope.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withSystemContext(callable $callback): mixed
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $callback();
        }

        $keys = [
            'fsa.role',
            'fsa.client_ids',
            'fsa.user_id',
            'fsa.report_id',
            'fsa.npo_engagement_id',
        ];

        $previous = [];
        foreach ($keys as $key) {
            $previous[$key] = DB::selectOne('SELECT current_setting(?, true) AS value', [$key])?->value;
        }

        DB::statement(
            'SELECT fsa_set_request_context(?, ?, ?, ?, ?)',
            ['system', '', null, null, null],
        );

        try {
            return $callback();
        } finally {
            DB::statement(
                'SELECT fsa_set_request_context(?, ?, ?, ?, ?)',
                [
                    $previous['fsa.role'] ?: '',
                    $previous['fsa.client_ids'] ?: '',
                    $previous['fsa.user_id'] ?: null,
                    $previous['fsa.report_id'] ?: null,
                    $previous['fsa.npo_engagement_id'] ?: null,
                ],
            );
        }
    }
}
