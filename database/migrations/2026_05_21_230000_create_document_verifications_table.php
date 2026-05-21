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
        Schema::create('document_verifications', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('questionnaire_response_id')->nullable()->constrained('questionnaire_responses')->nullOnDelete();
            $table->foreignUuid('questionnaire_answer_id')->nullable()->constrained('questionnaire_answers')->nullOnDelete();
            $table->foreignUuid('questionnaire_question_id')->nullable()->constrained('questionnaire_questions')->nullOnDelete();
            $table->string('claim_source', 80)->default('upload_context');
            $table->char('context_hash', 64);
            $table->text('question_prompt')->nullable();
            $table->text('claim_text');
            $table->string('outcome', 40)->default('pending');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->text('explanation')->nullable();
            $table->text('client_explanation')->nullable();
            $table->jsonb('source_payload')->nullable();
            $table->jsonb('ai_payload')->nullable();
            $table->string('prompt_version', 40)->nullable();
            $table->char('prompt_hash', 64)->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestampsTz();

            $table->unique(['document_id', 'context_hash']);
            $table->index(['client_id', 'outcome', 'resolved_at']);
            $table->index(['document_id', 'outcome']);
            $table->index('questionnaire_answer_id');
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('document_verifications');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE document_verifications ENABLE ROW LEVEL SECURITY;
            ALTER TABLE document_verifications FORCE ROW LEVEL SECURITY;

            CREATE POLICY document_verifications_client_scope ON document_verifications
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                );
        SQL);
    }
};
