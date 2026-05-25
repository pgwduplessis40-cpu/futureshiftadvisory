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
        Schema::create('conversion_outcomes', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('entrepreneur_profile_id')->nullable()->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('plan_assessment_id')->nullable()->constrained('plan_assessments')->nullOnDelete();
            $table->jsonb('outcome_signal');
            $table->timestampTz('observed_at');
            $table->timestampsTz();

            $table->index(['entrepreneur_profile_id', 'observed_at']);
            $table->index(['client_id', 'observed_at']);
            $table->index('plan_assessment_id');
        });

        $this->installPrivacyPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_outcomes');
    }

    private function installPrivacyPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE conversion_outcomes
            ADD CONSTRAINT conversion_outcomes_subject_check
            CHECK (entrepreneur_profile_id IS NOT NULL OR client_id IS NOT NULL)
        SQL);

        DB::unprepared(<<<'SQL'
            ALTER TABLE conversion_outcomes ENABLE ROW LEVEL SECURITY;
            ALTER TABLE conversion_outcomes FORCE ROW LEVEL SECURITY;

            CREATE POLICY conversion_outcomes_scope ON conversion_outcomes
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1 FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = conversion_outcomes.entrepreneur_profile_id
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
                        SELECT 1 FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = conversion_outcomes.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );
        SQL);
    }
};
