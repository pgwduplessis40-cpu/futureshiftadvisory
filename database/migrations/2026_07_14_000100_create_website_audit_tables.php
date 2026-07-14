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
        Schema::create('website_url_confirmations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->text('root_url');
            $table->string('status', 40)->default('confirmed');
            $table->jsonb('source_questionnaire_answer_ids')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
        });

        Schema::create('website_audit_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('analysis_run_id')->nullable()->constrained('analysis_runs')->nullOnDelete();
            $table->foreignUuid('website_url_confirmation_id')->nullable()->constrained('website_url_confirmations')->nullOnDelete();
            $table->text('root_url')->nullable();
            $table->timestampTz('fetched_at')->nullable();
            $table->jsonb('pages')->nullable();
            $table->jsonb('ai_evidence')->nullable();
            $table->jsonb('technical')->nullable();
            $table->jsonb('performance')->nullable();
            $table->jsonb('nz_compliance')->nullable();
            $table->jsonb('scores')->nullable();
            $table->string('fetch_status', 40);
            $table->string('skip_reason', 80)->nullable();
            $table->jsonb('source_attributions')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'fetched_at']);
            $table->index(['client_id', 'fetch_status']);
            $table->index('analysis_run_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('website_audit_snapshots');
        Schema::dropIfExists('website_url_confirmations');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['website_url_confirmations', 'website_audit_snapshots'] as $table) {
            DB::unprepared(<<<SQL
                ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;
                ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;

                CREATE POLICY {$table}_client_scope ON {$table}
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
    }
};
