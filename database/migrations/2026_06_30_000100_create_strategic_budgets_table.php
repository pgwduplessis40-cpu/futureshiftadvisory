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
        Schema::create('strategic_budgets', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('business_plan_id')->nullable()->constrained('business_plans')->nullOnDelete();
            $table->foreignUuid('proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
            $table->string('pathway', 40);
            $table->string('label', 120);
            $table->string('status', 40)->default('locked');
            $table->unsignedSmallInteger('horizon_months')->default(12);
            $table->unsignedSmallInteger('expected_runway_months')->nullable();
            $table->jsonb('source_financials')->nullable();
            $table->jsonb('client_goals')->nullable();
            $table->jsonb('advisor_goals')->nullable();
            $table->jsonb('assumptions')->nullable();
            $table->jsonb('implementation_costs')->nullable();
            $table->jsonb('monthly_fixed_costs')->nullable();
            $table->jsonb('future_costs')->nullable();
            $table->jsonb('revenue_forecast')->nullable();
            $table->jsonb('funding_sources')->nullable();
            $table->jsonb('funding_scenarios')->nullable();
            $table->jsonb('computed')->nullable();
            $table->jsonb('flags')->nullable();
            $table->jsonb('confidence')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('used_in_proposal_at')->nullable();
            $table->timestampTz('accepted_snapshot_at')->nullable();
            $table->timestampsTz();

            $table->unique(['client_id', 'pathway']);
            $table->index(['client_id', 'status']);
            $table->index('business_plan_id');
            $table->index('proposal_id');
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('strategic_budgets');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE strategic_budgets ENABLE ROW LEVEL SECURITY;
            ALTER TABLE strategic_budgets FORCE ROW LEVEL SECURITY;

            CREATE POLICY strategic_budgets_scope ON strategic_budgets
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
