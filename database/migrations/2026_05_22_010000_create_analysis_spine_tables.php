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
        Schema::create('analysis_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('module', 80);
            $table->string('status', 40)->default('queued');
            $table->jsonb('framework_lenses')->nullable();
            $table->jsonb('data_quality_snapshot')->nullable();
            $table->string('ai_model', 120)->nullable();
            $table->string('prompt_version', 80)->nullable();
            $table->char('prompt_hash', 64)->nullable();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'module', 'status']);
            $table->index(['status', 'started_at']);
            $table->index('created_by_user_id');
        });

        Schema::create('analysis_findings', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('lens', 40);
            $table->string('severity', 40);
            $table->string('title');
            $table->text('body');
            $table->jsonb('attributions');
            $table->string('document_support', 40)->default('none');
            $table->string('uncertainty', 20)->default('high');
            $table->text('data_quality_disclaimer')->nullable();
            $table->jsonb('bias_signals')->nullable();
            $table->uuid('pv_link_id')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'severity']);
            $table->index(['analysis_run_id', 'lens']);
            $table->index('pv_link_id');
        });

        Schema::create('analysis_feedback', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('analysis_finding_id')->constrained('analysis_findings')->cascadeOnDelete();
            $table->foreignId('advisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 40);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('corrected_body')->nullable();
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['analysis_finding_id', 'decision']);
            $table->index('advisor_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_feedback');
        Schema::dropIfExists('analysis_findings');
        Schema::dropIfExists('analysis_runs');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE analysis_runs ENABLE ROW LEVEL SECURITY;
            ALTER TABLE analysis_runs FORCE ROW LEVEL SECURITY;

            CREATE POLICY analysis_runs_client_scope ON analysis_runs
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE analysis_findings ENABLE ROW LEVEL SECURITY;
            ALTER TABLE analysis_findings FORCE ROW LEVEL SECURITY;

            CREATE POLICY analysis_findings_client_scope ON analysis_findings
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE analysis_feedback ENABLE ROW LEVEL SECURITY;
            ALTER TABLE analysis_feedback FORCE ROW LEVEL SECURITY;

            CREATE POLICY analysis_feedback_finding_scope ON analysis_feedback
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM analysis_findings
                        WHERE analysis_findings.id = analysis_feedback.analysis_finding_id
                        AND analysis_findings.client_id::text = ANY (fsa_current_client_ids())
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM analysis_findings
                        WHERE analysis_findings.id = analysis_feedback.analysis_finding_id
                        AND analysis_findings.client_id::text = ANY (fsa_current_client_ids())
                    )
                );
        SQL);
    }
};
