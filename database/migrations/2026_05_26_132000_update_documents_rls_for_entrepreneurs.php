<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS documents_client_scope ON documents;
            DROP POLICY IF EXISTS documents_scope ON documents;

            CREATE POLICY documents_scope ON documents
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = documents.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = documents.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS documents_scope ON documents;
            DROP POLICY IF EXISTS documents_client_scope ON documents;

            CREATE POLICY documents_client_scope ON documents
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                );
        SQL);
    }
};
