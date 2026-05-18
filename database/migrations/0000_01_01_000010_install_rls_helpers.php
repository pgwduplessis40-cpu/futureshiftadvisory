<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Installs the Postgres helper function used by the EnforceClientScope
 * middleware to set per-request session context for row-level security.
 *
 * Future per-table RLS policies will consult two session variables:
 *
 *   current_setting('fsa.role', true)          -- e.g. 'super_admin', 'advisor'
 *   current_setting('fsa.client_ids', true)    -- comma-separated UUIDs
 *
 * The helper function provides a single, audit-friendly entry point so the
 * raw set_config() calls don't leak into application code.
 *
 * Phase 1 ships only the helper; per-table policies are installed by the
 * migrations that create the tables themselves (clients, documents, etc.).
 *
 * See: PLAN.md section 6.2 and section 7.4; docs/architecture/postgres-rls.md;
 *      app/Http/Middleware/EnforceClientScope.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fsa_set_request_context(
                p_role text,
                p_client_ids text
            )
            RETURNS void
            LANGUAGE plpgsql
            AS $$
            BEGIN
                PERFORM set_config('fsa.role', COALESCE(p_role, ''), false);
                PERFORM set_config('fsa.client_ids', COALESCE(p_client_ids, ''), false);
            END;
            $$;

            COMMENT ON FUNCTION fsa_set_request_context(text, text) IS
                'Sets per-request session context for FSA row-level security policies. '
                'Called by the EnforceClientScope middleware on every authenticated request.';
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fsa_current_role()
            RETURNS text
            LANGUAGE sql
            STABLE
            AS $$
                SELECT NULLIF(current_setting('fsa.role', true), '');
            $$;

            COMMENT ON FUNCTION fsa_current_role() IS
                'Returns the FSA role of the current request, or NULL if unset.';

            CREATE OR REPLACE FUNCTION fsa_current_client_ids()
            RETURNS text[]
            LANGUAGE sql
            STABLE
            AS $$
                SELECT CASE
                    WHEN NULLIF(current_setting('fsa.client_ids', true), '') IS NULL THEN ARRAY[]::text[]
                    ELSE string_to_array(current_setting('fsa.client_ids', true), ',')
                END;
            $$;

            COMMENT ON FUNCTION fsa_current_client_ids() IS
                'Returns the FSA client_ids accessible in the current request as a text[].';
        SQL);
    }

    public function down(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS fsa_set_request_context(text, text);
            DROP FUNCTION IF EXISTS fsa_current_role();
            DROP FUNCTION IF EXISTS fsa_current_client_ids();
        SQL);
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
