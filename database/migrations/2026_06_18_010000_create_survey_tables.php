<?php

declare(strict_types=1);

use App\Enums\SurveyAssignmentStatus;
use App\Enums\SurveyQuestionType;
use App\Enums\SurveyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('key', 120);
            $table->string('version', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 40)->default(SurveyStatus::Draft->value);
            $table->jsonb('settings')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->unique(['key', 'version']);
            $table->index(['key', 'status']);
        });

        Schema::create('survey_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->unsignedSmallInteger('order');
            $table->string('type', 40);
            $table->string('key', 120);
            $table->text('prompt');
            $table->text('help_text')->nullable();
            $table->jsonb('options')->nullable();
            $table->boolean('required')->default(true);
            $table->timestampsTz();

            $table->unique(['survey_id', 'key']);
            $table->unique(['id', 'survey_id']);
            $table->index(['survey_id', 'order']);
            $table->index('type');
        });

        Schema::create('survey_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('survey_id')->constrained('surveys')->restrictOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('entrepreneur_profile_id')->nullable()->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->string('status', 40)->default(SurveyAssignmentStatus::Pending->value);
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('activated_at');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->jsonb('deliverable_snapshot');
            $table->timestampsTz();

            $table->unique(['id', 'survey_id']);
            $table->index(['client_id', 'status']);
            $table->index(['entrepreneur_profile_id', 'status']);
            $table->index(['survey_id', 'status']);
            $table->index('activated_by_user_id');
        });

        Schema::create('survey_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('survey_assignment_id')->constrained('survey_assignments')->cascadeOnDelete();
            $table->foreignUuid('survey_id')->constrained('surveys')->restrictOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('entrepreneur_profile_id')->nullable()->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('submitted_at');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->unsignedSmallInteger('nps_score')->nullable();
            $table->timestampsTz();

            $table->unique('survey_assignment_id');
            $table->unique(['id', 'survey_id']);
            $table->index(['client_id', 'submitted_at']);
            $table->index(['entrepreneur_profile_id', 'submitted_at'], 'survey_responses_entrepreneur_submitted_idx');
            $table->index(['survey_id', 'submitted_at']);
            $table->index('submitted_by_user_id');
        });

        Schema::create('survey_answers', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('response_id')->constrained('survey_responses')->cascadeOnDelete();
            $table->foreignUuid('question_id')->constrained('survey_questions')->restrictOnDelete();
            $table->foreignUuid('survey_id')->constrained('surveys')->restrictOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('entrepreneur_profile_id')->nullable()->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->jsonb('anchor_ref')->nullable();
            $table->string('answer_key', 40)->nullable();
            $table->jsonb('value')->nullable();
            $table->decimal('numeric_value', 8, 2)->nullable();
            $table->timestampsTz();

            $table->index(['response_id', 'question_id']);
            $table->index(['survey_id', 'question_id']);
            $table->index(['client_id', 'survey_id']);
            $table->index(['entrepreneur_profile_id', 'survey_id'], 'survey_answers_entrepreneur_survey_idx');
        });

        $this->installPostgresGuards();
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_assignments');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
    }

    private function installPostgresGuards(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $questionTypes = $this->quoted(SurveyQuestionType::values());
        $surveyStatuses = $this->quoted(SurveyStatus::values());
        $assignmentStatuses = $this->quoted([
            SurveyAssignmentStatus::Pending->value,
            SurveyAssignmentStatus::InProgress->value,
            SurveyAssignmentStatus::Completed->value,
            SurveyAssignmentStatus::Cancelled->value,
            SurveyAssignmentStatus::Expired->value,
        ]);

        DB::unprepared(<<<SQL
            ALTER TABLE surveys
                ADD CONSTRAINT surveys_status_check
                CHECK (status IN ({$surveyStatuses}));

            ALTER TABLE survey_questions
                ADD CONSTRAINT survey_questions_type_check
                CHECK (type IN ({$questionTypes}));

            ALTER TABLE survey_assignments
                ADD CONSTRAINT survey_assignments_subject_xor
                CHECK ((client_id IS NOT NULL)::integer + (entrepreneur_profile_id IS NOT NULL)::integer = 1),
                ADD CONSTRAINT survey_assignments_status_check
                CHECK (status IN ({$assignmentStatuses})),
                ADD CONSTRAINT survey_assignments_id_client_unique
                UNIQUE (id, client_id),
                ADD CONSTRAINT survey_assignments_id_entrepreneur_unique
                UNIQUE (id, entrepreneur_profile_id);

            ALTER TABLE survey_responses
                ADD CONSTRAINT survey_responses_subject_xor
                CHECK ((client_id IS NOT NULL)::integer + (entrepreneur_profile_id IS NOT NULL)::integer = 1),
                ADD CONSTRAINT survey_responses_assignment_client_fk
                FOREIGN KEY (survey_assignment_id, client_id)
                REFERENCES survey_assignments (id, client_id)
                ON DELETE CASCADE,
                ADD CONSTRAINT survey_responses_assignment_entrepreneur_fk
                FOREIGN KEY (survey_assignment_id, entrepreneur_profile_id)
                REFERENCES survey_assignments (id, entrepreneur_profile_id)
                ON DELETE CASCADE,
                ADD CONSTRAINT survey_responses_assignment_survey_fk
                FOREIGN KEY (survey_assignment_id, survey_id)
                REFERENCES survey_assignments (id, survey_id)
                ON DELETE CASCADE,
                ADD CONSTRAINT survey_responses_id_client_unique
                UNIQUE (id, client_id),
                ADD CONSTRAINT survey_responses_id_entrepreneur_unique
                UNIQUE (id, entrepreneur_profile_id);

            ALTER TABLE survey_answers
                ADD CONSTRAINT survey_answers_subject_xor
                CHECK ((client_id IS NOT NULL)::integer + (entrepreneur_profile_id IS NOT NULL)::integer = 1),
                ADD CONSTRAINT survey_answers_response_client_fk
                FOREIGN KEY (response_id, client_id)
                REFERENCES survey_responses (id, client_id)
                ON DELETE CASCADE,
                ADD CONSTRAINT survey_answers_response_entrepreneur_fk
                FOREIGN KEY (response_id, entrepreneur_profile_id)
                REFERENCES survey_responses (id, entrepreneur_profile_id)
                ON DELETE CASCADE,
                ADD CONSTRAINT survey_answers_response_survey_fk
                FOREIGN KEY (response_id, survey_id)
                REFERENCES survey_responses (id, survey_id)
                ON DELETE CASCADE,
                ADD CONSTRAINT survey_answers_question_survey_fk
                FOREIGN KEY (question_id, survey_id)
                REFERENCES survey_questions (id, survey_id)
                ON DELETE RESTRICT,
                ADD CONSTRAINT survey_answers_shape_check
                CHECK (
                    (
                        anchor_ref IS NULL
                        AND answer_key IS NULL
                    )
                    OR (
                        answer_key IN ('received', 'accessible', 'met_objective')
                        AND anchor_ref IS NOT NULL
                        AND anchor_ref->>'source_type' IN ('report', 'document', 'plan_assessment')
                        AND NULLIF(anchor_ref->>'source_id', '') IS NOT NULL
                    )
                );

            CREATE UNIQUE INDEX survey_answers_flat_unique
                ON survey_answers (response_id, question_id)
                WHERE anchor_ref IS NULL AND answer_key IS NULL;

            CREATE UNIQUE INDEX survey_answers_anchor_unique
                ON survey_answers (
                    response_id,
                    question_id,
                    (anchor_ref->>'source_type'),
                    (anchor_ref->>'source_id'),
                    answer_key
                )
                WHERE anchor_ref IS NOT NULL AND answer_key IS NOT NULL;
        SQL);

        DB::unprepared(<<<'SQL'
            ALTER TABLE survey_assignments ENABLE ROW LEVEL SECURITY;
            ALTER TABLE survey_assignments FORCE ROW LEVEL SECURITY;

            CREATE POLICY survey_assignments_select_scope ON survey_assignments
                FOR SELECT
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = survey_assignments.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            CREATE POLICY survey_assignments_admin_write ON survey_assignments
                USING (fsa_current_role() IN ('super_admin', 'system'))
                WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));

            ALTER TABLE survey_responses ENABLE ROW LEVEL SECURITY;
            ALTER TABLE survey_responses FORCE ROW LEVEL SECURITY;

            CREATE POLICY survey_responses_select_scope ON survey_responses
                FOR SELECT
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = survey_responses.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            CREATE POLICY survey_responses_insert_submitter ON survey_responses
                FOR INSERT
                WITH CHECK (
                    fsa_current_role() = 'system'
                    OR (
                        submitted_by_user_id::text = fsa_current_user_id()
                        AND EXISTS (
                            SELECT 1
                            FROM survey_assignments
                            WHERE survey_assignments.id = survey_responses.survey_assignment_id
                            AND survey_assignments.status IN ('pending', 'in_progress')
                            AND (
                                (
                                    survey_responses.client_id IS NOT NULL
                                    AND survey_assignments.client_id = survey_responses.client_id
                                    AND survey_responses.client_id::text = ANY (fsa_current_client_ids())
                                )
                                OR EXISTS (
                                    SELECT 1
                                    FROM entrepreneur_profiles
                                    WHERE entrepreneur_profiles.id = survey_responses.entrepreneur_profile_id
                                    AND survey_assignments.entrepreneur_profile_id = survey_responses.entrepreneur_profile_id
                                    AND entrepreneur_profiles.user_id::text = fsa_current_user_id()
                                )
                            )
                        )
                    )
                );

            ALTER TABLE survey_answers ENABLE ROW LEVEL SECURITY;
            ALTER TABLE survey_answers FORCE ROW LEVEL SECURITY;

            CREATE POLICY survey_answers_select_scope ON survey_answers
                FOR SELECT
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = survey_answers.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            CREATE POLICY survey_answers_insert_submitter ON survey_answers
                FOR INSERT
                WITH CHECK (
                    fsa_current_role() = 'system'
                    OR EXISTS (
                        SELECT 1
                        FROM survey_responses
                        JOIN survey_assignments
                            ON survey_assignments.id = survey_responses.survey_assignment_id
                        WHERE survey_responses.id = survey_answers.response_id
                        AND survey_responses.submitted_by_user_id::text = fsa_current_user_id()
                        AND survey_assignments.status IN ('pending', 'in_progress')
                    )
                );
        SQL);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function quoted(array $values): string
    {
        return collect($values)
            ->map(static fn (string $value): string => DB::getPdo()->quote($value))
            ->implode(', ');
    }
};
