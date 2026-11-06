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
        Schema::create('entrepreneur_forecast_models', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('business_plan_id')->unique()->constrained('business_plans')->cascadeOnDelete();
            $table->string('engine_version', 40);
            $table->unsignedInteger('revision')->default(1);
            $table->jsonb('drivers')->nullable();
            $table->jsonb('actuals')->nullable();
            $table->jsonb('reserve_policy')->nullable();
            // No computed column. This slice stores inputs only; outputs are
            // recomputed from them by the engine, and an unwritten output
            // column would invite a later slice to fill it from somewhere
            // other than the engine.
            // A saved model is not a selected model. Selection is its own
            // recorded act, so a reconstruction can be built and reviewed
            // without changing any downstream payload until an advisor
            // chooses it.
            $table->timestampTz('selected_at')->nullable();
            $table->foreignId('selected_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['business_plan_id', 'selected_at']);
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('entrepreneur_forecast_models');
    }

    /**
     * Mirrors the entrepreneur_budgets read scope. The write scope differs
     * deliberately: it includes the assigned advisor, because a version-2
     * reconstruction is an advisor decision rather than a founder entry.
     */
    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE entrepreneur_forecast_models ENABLE ROW LEVEL SECURITY;
            ALTER TABLE entrepreneur_forecast_models FORCE ROW LEVEL SECURITY;

            CREATE POLICY entrepreneur_forecast_models_scope ON entrepreneur_forecast_models
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM business_plans
                        LEFT JOIN entrepreneur_profiles
                            ON entrepreneur_profiles.id = business_plans.entrepreneur_profile_id
                        WHERE business_plans.id = entrepreneur_forecast_models.business_plan_id
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
                        WHERE business_plans.id = entrepreneur_forecast_models.business_plan_id
                        AND (
                            business_plans.client_id::text = ANY (fsa_current_client_ids())
                            OR entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                        )
                    )
                );
        SQL);
    }
};
