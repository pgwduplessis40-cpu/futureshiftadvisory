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
        Schema::create('questionnaires', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('set', 80);
            $table->string('version', 40);
            $table->string('title');
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['set', 'version']);
            $table->index(['set', 'published_at']);
        });

        Schema::create('questionnaire_sections', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('questionnaire_id')->constrained('questionnaires')->cascadeOnDelete();
            $table->unsignedSmallInteger('order');
            $table->string('title');
            $table->text('help_text')->nullable();
            $table->timestampsTz();

            $table->index(['questionnaire_id', 'order']);
        });

        Schema::create('questionnaire_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('questionnaire_section_id')->constrained('questionnaire_sections')->cascadeOnDelete();
            $table->unsignedSmallInteger('order');
            $table->string('type', 40);
            $table->text('prompt');
            $table->text('help_text')->nullable();
            $table->jsonb('options')->nullable();
            $table->jsonb('conditional_logic')->nullable();
            $table->boolean('required')->default(true);
            $table->timestampsTz();

            $table->index(['questionnaire_section_id', 'order']);
            $table->index('type');
        });

        Schema::create('questionnaire_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('questionnaire_id')->constrained('questionnaires')->restrictOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['client_id', 'questionnaire_id']);
            $table->index(['client_id', 'submitted_at']);
        });

        Schema::create('questionnaire_answers', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('response_id')->constrained('questionnaire_responses')->cascadeOnDelete();
            $table->foreignUuid('question_id')->constrained('questionnaire_questions')->restrictOnDelete();
            $table->jsonb('value')->nullable();
            $table->jsonb('attached_document_ids')->nullable();
            $table->timestampsTz();

            $table->unique(['response_id', 'question_id']);
            $table->index('question_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_answers');
        Schema::dropIfExists('questionnaire_responses');
        Schema::dropIfExists('questionnaire_questions');
        Schema::dropIfExists('questionnaire_sections');
        Schema::dropIfExists('questionnaires');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE questionnaire_responses ENABLE ROW LEVEL SECURITY;
            ALTER TABLE questionnaire_responses FORCE ROW LEVEL SECURITY;

            CREATE POLICY questionnaire_responses_client_scope ON questionnaire_responses
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE questionnaire_answers ENABLE ROW LEVEL SECURITY;
            ALTER TABLE questionnaire_answers FORCE ROW LEVEL SECURITY;

            CREATE POLICY questionnaire_answers_response_scope ON questionnaire_answers
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM questionnaire_responses
                        WHERE questionnaire_responses.id = questionnaire_answers.response_id
                        AND questionnaire_responses.client_id::text = ANY (fsa_current_client_ids())
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM questionnaire_responses
                        WHERE questionnaire_responses.id = questionnaire_answers.response_id
                        AND questionnaire_responses.client_id::text = ANY (fsa_current_client_ids())
                    )
                );
        SQL);
    }
};
