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
        Schema::create('succession_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('analysis_run_id')->nullable()->constrained('analysis_runs')->nullOnDelete();
            $table->unsignedTinyInteger('exit_readiness_score');
            $table->jsonb('options');
            $table->jsonb('owner_dependency_plan');
            $table->foreignUuid('target_exit_pv_calculation_id')->nullable()->constrained('pv_calculations')->nullOnDelete();
            $table->decimal('target_exit_pv', 16, 2)->default(0);
            $table->boolean('owner_readiness_is_primary_constraint')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'created_at']);
            $table->index('analysis_run_id');
            $table->index('target_exit_pv_calculation_id');
            $table->index('created_by_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('succession_plans');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE succession_plans ENABLE ROW LEVEL SECURITY;
            ALTER TABLE succession_plans FORCE ROW LEVEL SECURITY;

            CREATE POLICY succession_plans_client_scope ON succession_plans
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
