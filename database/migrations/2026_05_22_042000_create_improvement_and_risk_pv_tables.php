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
        Schema::create('improvement_opportunities', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('analysis_finding_id')->nullable()->constrained('analysis_findings')->nullOnDelete();
            $table->foreignUuid('pv_calculation_id')->constrained('pv_calculations')->cascadeOnDelete();
            $table->string('title');
            $table->decimal('annual_benefit', 16, 2);
            $table->unsignedTinyInteger('duration_years');
            $table->decimal('pv_of_impact', 16, 2);
            $table->unsignedInteger('rank');
            $table->jsonb('source_attributions');
            $table->timestampsTz();

            $table->index(['client_id', 'rank']);
            $table->index('analysis_finding_id');
            $table->index('pv_calculation_id');
        });

        Schema::create('risk_costs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('analysis_finding_id')->nullable()->constrained('analysis_findings')->nullOnDelete();
            $table->foreignUuid('pv_calculation_id')->constrained('pv_calculations')->cascadeOnDelete();
            $table->string('title');
            $table->decimal('financial_impact', 16, 2);
            $table->decimal('probability', 7, 4);
            $table->unsignedTinyInteger('duration_years');
            $table->jsonb('statutory_penalty_range')->nullable();
            $table->decimal('applied_impact', 16, 2);
            $table->decimal('annual_expected_cost', 16, 2);
            $table->decimal('pv_of_cost', 16, 2);
            $table->unsignedInteger('rank');
            $table->jsonb('source_attributions');
            $table->timestampsTz();

            $table->index(['client_id', 'rank']);
            $table->index('analysis_finding_id');
            $table->index('pv_calculation_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_costs');
        Schema::dropIfExists('improvement_opportunities');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE improvement_opportunities ENABLE ROW LEVEL SECURITY;
            ALTER TABLE improvement_opportunities FORCE ROW LEVEL SECURITY;

            CREATE POLICY improvement_opportunities_client_scope ON improvement_opportunities
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE risk_costs ENABLE ROW LEVEL SECURITY;
            ALTER TABLE risk_costs FORCE ROW LEVEL SECURITY;

            CREATE POLICY risk_costs_client_scope ON risk_costs
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
