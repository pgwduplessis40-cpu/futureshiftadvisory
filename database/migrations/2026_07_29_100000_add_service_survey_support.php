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
        Schema::table('surveys', function (Blueprint $table): void {
            $table->string('type', 40)->default('general_experience');
            $table->index(['type', 'status']);
        });

        Schema::table('survey_assignments', function (Blueprint $table): void {
            $table->foreignUuid('service_activation_id')
                ->nullable()
                ->constrained('service_activations')
                ->restrictOnDelete();
            $table->jsonb('service_snapshot')->nullable();
            $table->index('service_activation_id');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE surveys
                ADD CONSTRAINT surveys_type_check
                CHECK (type IN ('general_experience', 'service_improvement'));

            ALTER TABLE survey_questions
                DROP CONSTRAINT survey_questions_type_check,
                ADD CONSTRAINT survey_questions_type_check
                CHECK (type IN ('likert', 'nps', 'boolean', 'anchored_matrix', 'text'));

            CREATE OR REPLACE FUNCTION fsa_assert_survey_assignment_service_contract()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            DECLARE
                survey_type_value text;
                activation_client_id uuid;
            BEGIN
                SELECT type INTO survey_type_value
                FROM surveys
                WHERE id = NEW.survey_id;

                IF survey_type_value = 'service_improvement' THEN
                    IF NEW.client_id IS NULL
                        OR NEW.entrepreneur_profile_id IS NOT NULL
                        OR NEW.service_activation_id IS NULL
                        OR NEW.service_snapshot IS NULL
                        OR jsonb_typeof(NEW.service_snapshot) <> 'object' THEN
                        RAISE EXCEPTION 'Service improvement surveys require a client service activation snapshot.';
                    END IF;

                    SELECT client_id INTO activation_client_id
                    FROM service_activations
                    WHERE id = NEW.service_activation_id
                    FOR KEY SHARE;

                    IF activation_client_id IS NULL OR activation_client_id <> NEW.client_id THEN
                        RAISE EXCEPTION 'Survey service activation must belong to the assignment client.';
                    END IF;
                ELSIF NEW.service_activation_id IS NOT NULL OR NEW.service_snapshot IS NOT NULL THEN
                    RAISE EXCEPTION 'Only service improvement surveys may reference a service activation.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER survey_assignments_service_contract
                BEFORE INSERT OR UPDATE OF survey_id, client_id, entrepreneur_profile_id, service_activation_id, service_snapshot
                ON survey_assignments
                FOR EACH ROW
                EXECUTE FUNCTION fsa_assert_survey_assignment_service_contract();

            CREATE UNIQUE INDEX survey_assignments_one_open_service_survey
                ON survey_assignments (service_activation_id)
                WHERE service_activation_id IS NOT NULL
                    AND status IN ('pending', 'in_progress');
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP INDEX IF EXISTS survey_assignments_one_open_service_survey;
                DROP TRIGGER IF EXISTS survey_assignments_service_contract ON survey_assignments;
                DROP FUNCTION IF EXISTS fsa_assert_survey_assignment_service_contract();

                ALTER TABLE survey_questions
                    DROP CONSTRAINT IF EXISTS survey_questions_type_check,
                    ADD CONSTRAINT survey_questions_type_check
                    CHECK (type IN ('likert', 'nps', 'boolean', 'anchored_matrix'));

                ALTER TABLE surveys
                    DROP CONSTRAINT IF EXISTS surveys_type_check;
            SQL);
        }

        Schema::table('survey_assignments', function (Blueprint $table): void {
            $table->dropForeign(['service_activation_id']);
            $table->dropIndex(['service_activation_id']);
            $table->dropColumn(['service_activation_id', 'service_snapshot']);
        });

        Schema::table('surveys', function (Blueprint $table): void {
            $table->dropIndex(['type', 'status']);
            $table->dropColumn('type');
        });
    }
};
