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
        Schema::create('dd_risk_register', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('dd_engagement_id')->constrained('dd_engagements')->cascadeOnDelete();
            $table->foreignUuid('analysis_finding_id')->nullable()->constrained('analysis_findings')->nullOnDelete();
            $table->foreignUuid('risk_cost_id')->nullable()->constrained('risk_costs')->nullOnDelete();
            $table->string('risk_level', 40);
            $table->string('category', 80);
            $table->string('title');
            $table->text('body');
            $table->decimal('financial_impact', 16, 2)->default(0);
            $table->decimal('probability', 7, 4)->default(0);
            $table->decimal('pv_of_cost', 16, 2)->default(0);
            $table->decimal('price_adjustment_nzd', 16, 2)->default(0);
            $table->unsignedInteger('rank');
            $table->string('status', 40)->default('open');
            $table->jsonb('source_attributions');
            $table->timestampsTz();

            $table->index(['client_id', 'rank']);
            $table->index(['dd_engagement_id', 'rank']);
            $table->index('analysis_finding_id');
            $table->index('risk_cost_id');
        });

        Schema::create('dd_integration_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('dd_engagement_id')->constrained('dd_engagements')->cascadeOnDelete();
            $table->foreignUuid('dd_risk_register_id')->nullable()->constrained('dd_risk_register')->nullOnDelete();
            $table->unsignedSmallInteger('day');
            $table->string('phase', 80);
            $table->text('action');
            $table->string('owner', 120)->nullable();
            $table->string('priority', 40)->default('medium');
            $table->string('status', 40)->default('pending');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'day']);
            $table->index(['dd_engagement_id', 'day']);
            $table->index('dd_risk_register_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('dd_integration_plans');
        Schema::dropIfExists('dd_risk_register');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE dd_risk_register ENABLE ROW LEVEL SECURITY;
            ALTER TABLE dd_risk_register FORCE ROW LEVEL SECURITY;

            CREATE POLICY dd_risk_register_client_scope ON dd_risk_register
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE dd_integration_plans ENABLE ROW LEVEL SECURITY;
            ALTER TABLE dd_integration_plans FORCE ROW LEVEL SECURITY;

            CREATE POLICY dd_integration_plans_client_scope ON dd_integration_plans
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
