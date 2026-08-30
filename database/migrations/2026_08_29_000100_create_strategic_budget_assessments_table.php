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
        Schema::create('strategic_budget_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('strategic_budget_id')->constrained('strategic_budgets')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedInteger('round');
            $table->string('status', 40)->default('submitted');
            $table->jsonb('snapshot')->default('{}');
            $table->jsonb('assessment_criteria')->default('[]');
            $table->jsonb('scores')->default('{}');
            $table->jsonb('priorities')->default('[]');
            $table->text('suggested_feedback')->nullable();
            $table->text('suggested_reply')->nullable();
            $table->text('advisor_feedback')->nullable();
            $table->text('proposed_reply')->nullable();
            $table->jsonb('feedback_snapshot')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assessed_at')->nullable();
            $table->foreignId('assessed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('feedback_saved_at')->nullable();
            $table->foreignId('feedback_saved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('feedback_sent_at')->nullable();
            $table->foreignId('feedback_sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('client_message_thread_id')->nullable()->constrained('message_threads')->nullOnDelete();
            $table->foreignUuid('client_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['strategic_budget_id', 'round']);
            $table->index(['client_id', 'status']);
            $table->index(['strategic_budget_id', 'submitted_at']);
            $table->index('client_message_thread_id');
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('strategic_budget_assessments');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE strategic_budget_assessments ENABLE ROW LEVEL SECURITY;
            ALTER TABLE strategic_budget_assessments FORCE ROW LEVEL SECURITY;

            CREATE POLICY strategic_budget_assessments_scope ON strategic_budget_assessments
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
