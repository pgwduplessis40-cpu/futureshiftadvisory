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
        Schema::create('plan_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_plan_id')->constrained('business_plans')->cascadeOnDelete();
            $table->unsignedInteger('round');
            $table->timestampTz('submitted_at');
            $table->jsonb('progress_comparison');
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['business_plan_id', 'round']);
            $table->index(['business_plan_id', 'submitted_at']);
            $table->index('submitted_by_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_revisions');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE plan_revisions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE plan_revisions FORCE ROW LEVEL SECURITY;

            CREATE POLICY plan_revisions_scope ON plan_revisions
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM business_plans
                        LEFT JOIN entrepreneur_profiles
                            ON entrepreneur_profiles.id = business_plans.entrepreneur_profile_id
                        WHERE business_plans.id = plan_revisions.business_plan_id
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
                        SELECT 1
                        FROM business_plans
                        LEFT JOIN entrepreneur_profiles
                            ON entrepreneur_profiles.id = business_plans.entrepreneur_profile_id
                        WHERE business_plans.id = plan_revisions.business_plan_id
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
