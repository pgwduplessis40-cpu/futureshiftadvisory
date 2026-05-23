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
        Schema::create('plan_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_plan_id')->constrained('business_plans')->cascadeOnDelete();
            $table->unsignedInteger('round')->default(1);
            $table->foreignUuid('rating_framework_id')->constrained('rating_frameworks')->restrictOnDelete();
            $table->jsonb('ai_scores');
            $table->jsonb('advisor_scores')->nullable();
            $table->jsonb('mentor_notes')->nullable();
            $table->jsonb('document_support')->nullable();
            $table->string('overall_grade', 40);
            $table->foreignUuid('concept_pv_calculation_id')->nullable()->constrained('pv_calculations')->nullOnDelete();
            $table->timestampTz('finalised_at')->nullable();
            $table->foreignId('finalised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['business_plan_id', 'round']);
            $table->index(['rating_framework_id', 'overall_grade']);
            $table->index('finalised_at');
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_assessments');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE plan_assessments ENABLE ROW LEVEL SECURITY;
            ALTER TABLE plan_assessments FORCE ROW LEVEL SECURITY;

            CREATE POLICY plan_assessments_scope ON plan_assessments
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM business_plans
                        JOIN entrepreneur_profiles
                            ON entrepreneur_profiles.id = business_plans.entrepreneur_profile_id
                        WHERE business_plans.id = plan_assessments.business_plan_id
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
                        FROM business_plans
                        JOIN entrepreneur_profiles
                            ON entrepreneur_profiles.id = business_plans.entrepreneur_profile_id
                        WHERE business_plans.id = plan_assessments.business_plan_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );
        SQL);
    }
};
