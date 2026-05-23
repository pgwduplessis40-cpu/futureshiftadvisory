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
        Schema::create('post_acquisition_migrations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('dd_engagement_id')->unique()->constrained('dd_engagements')->cascadeOnDelete();
            $table->foreignUuid('buyer_client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('advisory_client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('business_plan_id')->nullable()->constrained('business_plans')->nullOnDelete();
            $table->foreignUuid('dd_report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->foreignUuid('gap_questionnaire_response_id')->nullable()->constrained('questionnaire_responses')->nullOnDelete();
            $table->foreignUuid('proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
            $table->jsonb('migrated_document_ids');
            $table->decimal('dd_pv_baseline', 16, 2)->default(0);
            $table->string('status', 40)->default('created');
            $table->jsonb('metadata')->nullable();
            $table->foreignId('migrated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('migrated_at');
            $table->timestampsTz();

            $table->index('buyer_client_id');
            $table->index('advisory_client_id');
            $table->index('business_plan_id');
            $table->index('dd_report_id');
            $table->index('gap_questionnaire_response_id');
            $table->index('proposal_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('post_acquisition_migrations');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE post_acquisition_migrations ENABLE ROW LEVEL SECURITY;
            ALTER TABLE post_acquisition_migrations FORCE ROW LEVEL SECURITY;

            CREATE POLICY post_acquisition_migrations_client_scope ON post_acquisition_migrations
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR buyer_client_id::text = ANY (fsa_current_client_ids())
                    OR advisory_client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR buyer_client_id::text = ANY (fsa_current_client_ids())
                    OR advisory_client_id::text = ANY (fsa_current_client_ids())
                );
        SQL);
    }
};
