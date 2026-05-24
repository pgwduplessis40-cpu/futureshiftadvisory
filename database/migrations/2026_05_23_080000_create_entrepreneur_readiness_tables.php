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
        Schema::table('coaching_signals', function (Blueprint $table): void {
            $table->foreignUuid('entrepreneur_profile_id')
                ->nullable()
                ->after('client_id')
                ->constrained('entrepreneur_profiles')
                ->cascadeOnDelete();
            $table->index('entrepreneur_profile_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE coaching_signals ALTER COLUMN client_id DROP NOT NULL');
            DB::statement(<<<'SQL'
                ALTER TABLE coaching_signals
                ADD CONSTRAINT coaching_signals_subject_present
                CHECK (client_id IS NOT NULL OR entrepreneur_profile_id IS NOT NULL)
            SQL);
        }

        Schema::create('readiness_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('entrepreneur_profile_id')->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->jsonb('responses');
            $table->decimal('score', 5, 2)->default(0);
            $table->string('outcome', 40);
            $table->jsonb('personal_barriers')->nullable();
            $table->foreignId('assessed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assessed_at');
            $table->timestampsTz();

            $table->index(['entrepreneur_profile_id', 'assessed_at']);
            $table->index('outcome');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('readiness_assessments');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->installLegacyCoachingSignalsPolicy();

            DB::statement('ALTER TABLE coaching_signals DROP CONSTRAINT IF EXISTS coaching_signals_subject_present');
            DB::statement('DELETE FROM coaching_signals WHERE client_id IS NULL');
            DB::statement('ALTER TABLE coaching_signals ALTER COLUMN client_id SET NOT NULL');
        }

        Schema::table('coaching_signals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('entrepreneur_profile_id');
        });
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fsa_current_user_id()
            RETURNS text
            LANGUAGE sql
            STABLE
            AS $$
                SELECT NULLIF(current_setting('fsa.user_id', true), '');
            $$;

            ALTER TABLE entrepreneur_profiles ENABLE ROW LEVEL SECURITY;
            ALTER TABLE entrepreneur_profiles FORCE ROW LEVEL SECURITY;

            DROP POLICY IF EXISTS entrepreneur_profiles_scope ON entrepreneur_profiles;
            CREATE POLICY entrepreneur_profiles_scope ON entrepreneur_profiles
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR assigned_advisor_id::text = fsa_current_user_id()
                    OR user_id::text = fsa_current_user_id()
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR assigned_advisor_id::text = fsa_current_user_id()
                    OR user_id::text = fsa_current_user_id()
                );

            ALTER TABLE readiness_assessments ENABLE ROW LEVEL SECURITY;
            ALTER TABLE readiness_assessments FORCE ROW LEVEL SECURITY;

            CREATE POLICY readiness_assessments_scope ON readiness_assessments
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = readiness_assessments.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = readiness_assessments.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            DROP POLICY IF EXISTS coaching_signals_scope ON coaching_signals;
            CREATE POLICY coaching_signals_scope ON coaching_signals
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM client_team
                        WHERE client_team.client_id = coaching_signals.client_id
                        AND client_team.user_id::text = fsa_current_user_id()
                        AND client_team.role = 'lead_advisor'
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = coaching_signals.entrepreneur_profile_id
                        AND entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                );
        SQL);
    }

    private function installLegacyCoachingSignalsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS coaching_signals_scope ON coaching_signals;
            CREATE POLICY coaching_signals_scope ON coaching_signals
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM client_team
                        WHERE client_team.client_id = coaching_signals.client_id
                        AND client_team.user_id::text = fsa_current_user_id()
                        AND client_team.role = 'lead_advisor'
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                );
        SQL);
    }
};
