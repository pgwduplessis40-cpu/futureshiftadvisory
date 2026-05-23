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
        Schema::create('business_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('entrepreneur_profile_id')->nullable()->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->foreignUuid('dd_engagement_id')->nullable()->constrained('dd_engagements')->cascadeOnDelete();
            $table->string('title');
            $table->string('source_type', 40);
            $table->string('status', 40)->default('draft');
            $table->unsignedSmallInteger('current_phase')->default(1);
            $table->jsonb('founding_advisory_payload')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index(['entrepreneur_profile_id', 'status']);
            $table->index(['dd_engagement_id', 'status']);
            $table->index('created_by_user_id');
        });

        Schema::create('plan_phases', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_plan_id')->constrained('business_plans')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('title');
            $table->unsignedSmallInteger('position');
            $table->jsonb('depends_on')->nullable();
            $table->string('status', 40)->default('pending');
            $table->timestampsTz();

            $table->unique(['business_plan_id', 'key']);
            $table->index(['business_plan_id', 'position']);
        });

        Schema::create('plan_sections', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_plan_id')->constrained('business_plans')->cascadeOnDelete();
            $table->foreignUuid('plan_phase_id')->constrained('plan_phases')->cascadeOnDelete();
            $table->string('key', 160);
            $table->string('title');
            $table->text('body');
            $table->string('source_type', 40);
            $table->foreignUuid('source_analysis_finding_id')->nullable()->constrained('analysis_findings')->nullOnDelete();
            $table->string('completeness_status', 40)->default('draft');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['business_plan_id', 'key']);
            $table->index(['business_plan_id', 'completeness_status']);
            $table->index('plan_phase_id');
            $table->index('source_analysis_finding_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE business_plans
                ADD CONSTRAINT business_plans_owner_xor
                CHECK ((entrepreneur_profile_id IS NOT NULL) <> (dd_engagement_id IS NOT NULL))
            SQL);
        }

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_sections');
        Schema::dropIfExists('plan_phases');
        Schema::dropIfExists('business_plans');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE business_plans ENABLE ROW LEVEL SECURITY;
            ALTER TABLE business_plans FORCE ROW LEVEL SECURITY;

            CREATE POLICY business_plans_scope ON business_plans
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1 FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = business_plans.entrepreneur_profile_id
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
                        WHERE entrepreneur_profiles.id = business_plans.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            ALTER TABLE plan_phases ENABLE ROW LEVEL SECURITY;
            ALTER TABLE plan_phases FORCE ROW LEVEL SECURITY;

            CREATE POLICY plan_phases_scope ON plan_phases
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1 FROM business_plans
                        LEFT JOIN entrepreneur_profiles
                            ON entrepreneur_profiles.id = business_plans.entrepreneur_profile_id
                        WHERE business_plans.id = plan_phases.business_plan_id
                        AND (
                            business_plans.client_id::text = ANY (fsa_current_client_ids())
                            OR entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1 FROM business_plans
                        LEFT JOIN entrepreneur_profiles
                            ON entrepreneur_profiles.id = business_plans.entrepreneur_profile_id
                        WHERE business_plans.id = plan_phases.business_plan_id
                        AND (
                            business_plans.client_id::text = ANY (fsa_current_client_ids())
                            OR entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            ALTER TABLE plan_sections ENABLE ROW LEVEL SECURITY;
            ALTER TABLE plan_sections FORCE ROW LEVEL SECURITY;

            CREATE POLICY plan_sections_scope ON plan_sections
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1 FROM business_plans
                        LEFT JOIN entrepreneur_profiles
                            ON entrepreneur_profiles.id = business_plans.entrepreneur_profile_id
                        WHERE business_plans.id = plan_sections.business_plan_id
                        AND (
                            business_plans.client_id::text = ANY (fsa_current_client_ids())
                            OR entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1 FROM business_plans
                        LEFT JOIN entrepreneur_profiles
                            ON entrepreneur_profiles.id = business_plans.entrepreneur_profile_id
                        WHERE business_plans.id = plan_sections.business_plan_id
                        AND (
                            business_plans.client_id::text = ANY (fsa_current_client_ids())
                            OR entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );
        SQL);
    }
};
