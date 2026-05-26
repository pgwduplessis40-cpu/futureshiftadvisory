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
        Schema::create('governance_review_findings', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->uuid('npo_engagement_id');
            $table->string('finding_key', 80);
            $table->string('category', 80);
            $table->string('severity', 40);
            $table->string('title', 180);
            $table->text('body');
            $table->jsonb('criteria');
            $table->jsonb('evidence');
            $table->jsonb('attributions');
            $table->string('uncertainty', 40);
            $table->jsonb('ai_payload')->nullable();
            $table->string('status', 40)->default('pending_advisor_review');
            $table->text('advisor_notes')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['client_id', 'npo_engagement_id', 'finding_key'], 'governance_review_findings_unique_key');
            $table->index(['client_id', 'status']);
            $table->index(['npo_engagement_id', 'status']);
            $table->index(['category', 'severity']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE governance_review_findings
                ADD CONSTRAINT governance_review_findings_engagement_client_fk
                FOREIGN KEY (npo_engagement_id, client_id)
                REFERENCES npo_engagements (id, client_id)
                ON DELETE CASCADE
            SQL);
        }

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_review_findings');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE governance_review_findings ENABLE ROW LEVEL SECURITY;
            ALTER TABLE governance_review_findings FORCE ROW LEVEL SECURITY;

            CREATE POLICY governance_review_findings_client_scope ON governance_review_findings
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
