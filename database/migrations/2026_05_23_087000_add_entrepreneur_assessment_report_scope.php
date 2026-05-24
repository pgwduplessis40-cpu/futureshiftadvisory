<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->foreignUuid('entrepreneur_profile_id')
                ->nullable()
                ->after('client_id')
                ->constrained('entrepreneur_profiles')
                ->cascadeOnDelete();
            $table->index(['entrepreneur_profile_id', 'type', 'generated_at'], 'reports_entrepreneur_type_generated_idx');
        });

        Schema::table('report_sections', function (Blueprint $table): void {
            $table->foreignUuid('entrepreneur_profile_id')
                ->nullable()
                ->after('client_id')
                ->constrained('entrepreneur_profiles')
                ->cascadeOnDelete();
            $table->index(['entrepreneur_profile_id', 'key'], 'report_sections_entrepreneur_key_idx');
        });

        Schema::table('pv_calculations', function (Blueprint $table): void {
            $table->foreignUuid('entrepreneur_profile_id')
                ->nullable()
                ->after('client_id')
                ->constrained('entrepreneur_profiles')
                ->cascadeOnDelete();
            $table->index(['entrepreneur_profile_id', 'type', 'as_at'], 'pv_calculations_entrepreneur_type_as_at_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reports ALTER COLUMN client_id DROP NOT NULL');
            DB::statement('ALTER TABLE report_sections ALTER COLUMN client_id DROP NOT NULL');
            DB::statement('ALTER TABLE pv_calculations ALTER COLUMN client_id DROP NOT NULL');

            DB::statement(<<<'SQL'
                ALTER TABLE reports
                ADD CONSTRAINT reports_subject_present
                CHECK (client_id IS NOT NULL OR entrepreneur_profile_id IS NOT NULL)
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE report_sections
                ADD CONSTRAINT report_sections_subject_present
                CHECK (client_id IS NOT NULL OR entrepreneur_profile_id IS NOT NULL)
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE pv_calculations
                ADD CONSTRAINT pv_calculations_subject_present
                CHECK (client_id IS NOT NULL OR entrepreneur_profile_id IS NOT NULL)
            SQL);
        }

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->installLegacyRlsPolicies();

            DB::statement('ALTER TABLE pv_calculations DROP CONSTRAINT IF EXISTS pv_calculations_subject_present');
            DB::statement('ALTER TABLE report_sections DROP CONSTRAINT IF EXISTS report_sections_subject_present');
            DB::statement('ALTER TABLE reports DROP CONSTRAINT IF EXISTS reports_subject_present');
            DB::statement('DELETE FROM pv_calculations WHERE client_id IS NULL');
            DB::statement('DELETE FROM report_sections WHERE client_id IS NULL');
            DB::statement('DELETE FROM reports WHERE client_id IS NULL');
            DB::statement('ALTER TABLE pv_calculations ALTER COLUMN client_id SET NOT NULL');
            DB::statement('ALTER TABLE report_sections ALTER COLUMN client_id SET NOT NULL');
            DB::statement('ALTER TABLE reports ALTER COLUMN client_id SET NOT NULL');
        }

        Schema::table('pv_calculations', function (Blueprint $table): void {
            $table->dropIndex('pv_calculations_entrepreneur_type_as_at_idx');
            $table->dropConstrainedForeignId('entrepreneur_profile_id');
        });

        Schema::table('report_sections', function (Blueprint $table): void {
            $table->dropIndex('report_sections_entrepreneur_key_idx');
            $table->dropConstrainedForeignId('entrepreneur_profile_id');
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->dropIndex('reports_entrepreneur_type_generated_idx');
            $table->dropConstrainedForeignId('entrepreneur_profile_id');
        });

    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS reports_client_scope ON reports;
            CREATE POLICY reports_client_scope ON reports
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = reports.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = reports.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            DROP POLICY IF EXISTS report_sections_client_scope ON report_sections;
            CREATE POLICY report_sections_client_scope ON report_sections
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = report_sections.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = report_sections.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            DROP POLICY IF EXISTS pv_calculations_client_scope ON pv_calculations;
            CREATE POLICY pv_calculations_client_scope ON pv_calculations
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = pv_calculations.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = pv_calculations.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );
        SQL);
    }

    private function installLegacyRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS reports_client_scope ON reports;
            CREATE POLICY reports_client_scope ON reports
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            DROP POLICY IF EXISTS report_sections_client_scope ON report_sections;
            CREATE POLICY report_sections_client_scope ON report_sections
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            DROP POLICY IF EXISTS pv_calculations_client_scope ON pv_calculations;
            CREATE POLICY pv_calculations_client_scope ON pv_calculations
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );
        SQL);
    }
};
