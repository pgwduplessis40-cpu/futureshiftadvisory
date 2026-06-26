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
        Schema::create('entrepreneur_budgets', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_plan_id')->unique()->constrained('business_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('expected_runway_months')->nullable();
            $table->string('status', 40)->default('not_started');
            $table->jsonb('launch_costs')->nullable();
            $table->jsonb('monthly_fixed_costs')->nullable();
            $table->jsonb('revenue_forecast')->nullable();
            $table->jsonb('funding_sources')->nullable();
            $table->jsonb('computed')->nullable();
            $table->jsonb('flags')->nullable();
            $table->timestampTz('advisor_line_nudge_seen_at')->nullable();
            $table->timestampsTz();

            $table->index(['business_plan_id', 'status']);
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('entrepreneur_budgets');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE entrepreneur_budgets ENABLE ROW LEVEL SECURITY;
            ALTER TABLE entrepreneur_budgets FORCE ROW LEVEL SECURITY;

            CREATE POLICY entrepreneur_budgets_scope ON entrepreneur_budgets
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM business_plans
                        LEFT JOIN entrepreneur_profiles
                            ON entrepreneur_profiles.id = business_plans.entrepreneur_profile_id
                        WHERE business_plans.id = entrepreneur_budgets.business_plan_id
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
                        WHERE business_plans.id = entrepreneur_budgets.business_plan_id
                        AND (
                            business_plans.client_id::text = ANY (fsa_current_client_ids())
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );
        SQL);
    }
};
