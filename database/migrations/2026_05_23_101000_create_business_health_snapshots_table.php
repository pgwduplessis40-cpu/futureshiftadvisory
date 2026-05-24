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
        Schema::create('business_health_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->uuid('assessment_batch_id');
            $table->string('dimension', 40);
            $table->unsignedTinyInteger('score')->nullable();
            $table->foreignUuid('top_finding_id')->nullable()->constrained('analysis_findings')->nullOnDelete();
            $table->jsonb('contributing_finding_ids');
            $table->jsonb('module_run_states');
            $table->string('dimension_run_state', 80);
            $table->timestampTz('captured_at');
            $table->jsonb('source_attributions');
            $table->timestampsTz();

            $table->unique(['client_id', 'assessment_batch_id', 'dimension'], 'business_health_batch_dimension_unique');
            $table->index(['client_id', 'captured_at', 'assessment_batch_id'], 'business_health_latest_batch_index');
            $table->index('top_finding_id');
        });

        $this->installPostgresConstraints();
        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('business_health_snapshots');
    }

    private function installPostgresConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE business_health_snapshots
                ADD CONSTRAINT business_health_dimension_check
                    CHECK (dimension IN ('financial', 'operational', 'people', 'strategic', 'compliance')),
                ADD CONSTRAINT business_health_score_check
                    CHECK (score IS NULL OR score BETWEEN 0 AND 100),
                ADD CONSTRAINT business_health_dimension_state_check
                    CHECK (dimension_run_state IN (
                        'scored',
                        'completed_no_findings',
                        'completed_no_client_safe_findings',
                        'never_run',
                        'in_progress',
                        'blocked',
                        'failed'
                    ));
        SQL);
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE business_health_snapshots ENABLE ROW LEVEL SECURITY;
            ALTER TABLE business_health_snapshots FORCE ROW LEVEL SECURITY;

            CREATE POLICY business_health_snapshots_client_scope ON business_health_snapshots
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
