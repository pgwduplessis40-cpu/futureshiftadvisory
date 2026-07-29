<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
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
                    IF NEW.service_snapshot IS NULL OR jsonb_typeof(NEW.service_snapshot) <> 'object' THEN
                        RAISE EXCEPTION 'Service improvement surveys require a service snapshot.';
                    END IF;

                    IF NEW.service_activation_id IS NOT NULL THEN
                        IF NEW.client_id IS NULL OR NEW.entrepreneur_profile_id IS NOT NULL THEN
                            RAISE EXCEPTION 'Service activation surveys require the matching client.';
                        END IF;

                        SELECT client_id INTO activation_client_id
                        FROM service_activations
                        WHERE id = NEW.service_activation_id
                        FOR KEY SHARE;

                        IF activation_client_id IS NULL OR activation_client_id <> NEW.client_id THEN
                            RAISE EXCEPTION 'Survey service activation must belong to the assignment client.';
                        END IF;
                    ELSIF NEW.entrepreneur_profile_id IS NULL
                        OR NEW.client_id IS NOT NULL
                        OR COALESCE(NEW.service_snapshot->>'source', '') NOT IN ('entrepreneur_profile', 'idea_validation') THEN
                        RAISE EXCEPTION 'Entrepreneur service surveys require an entrepreneur service snapshot.';
                    ELSIF NEW.service_snapshot->>'source' = 'idea_validation'
                        AND (
                            COALESCE(NEW.service_snapshot->>'idea_validation_id', '') = ''
                            OR NOT EXISTS (
                                SELECT 1
                                FROM idea_validations
                                WHERE idea_validations.id::text = NEW.service_snapshot->>'idea_validation_id'
                                    AND idea_validations.entrepreneur_profile_id = NEW.entrepreneur_profile_id
                                    AND idea_validations.advisor_gate_passed_at IS NOT NULL
                            )
                        ) THEN
                        RAISE EXCEPTION 'Idea validation service surveys require a gate-approved idea validation for the entrepreneur.';
                    END IF;
                ELSIF NEW.service_activation_id IS NOT NULL OR NEW.service_snapshot IS NOT NULL THEN
                    RAISE EXCEPTION 'Only service improvement surveys may reference a service activation.';
                END IF;

                RETURN NEW;
            END;
            $$;

            DROP INDEX IF EXISTS survey_assignments_one_open_entrepreneur_service_survey;

            CREATE UNIQUE INDEX survey_assignments_one_open_entrepreneur_service_survey
                ON survey_assignments (entrepreneur_profile_id)
                WHERE entrepreneur_profile_id IS NOT NULL
                    AND service_activation_id IS NULL
                    AND service_snapshot IS NOT NULL
                    AND status IN ('pending', 'in_progress');
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
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
                    IF NEW.service_snapshot IS NULL OR jsonb_typeof(NEW.service_snapshot) <> 'object' THEN
                        RAISE EXCEPTION 'Service improvement surveys require a service snapshot.';
                    END IF;

                    IF NEW.service_activation_id IS NOT NULL THEN
                        IF NEW.client_id IS NULL OR NEW.entrepreneur_profile_id IS NOT NULL THEN
                            RAISE EXCEPTION 'Service activation surveys require the matching client.';
                        END IF;

                        SELECT client_id INTO activation_client_id
                        FROM service_activations
                        WHERE id = NEW.service_activation_id
                        FOR KEY SHARE;

                        IF activation_client_id IS NULL OR activation_client_id <> NEW.client_id THEN
                            RAISE EXCEPTION 'Survey service activation must belong to the assignment client.';
                        END IF;
                    ELSIF NEW.entrepreneur_profile_id IS NULL
                        OR NEW.client_id IS NOT NULL
                        OR NEW.service_snapshot->>'source' <> 'entrepreneur_profile' THEN
                        RAISE EXCEPTION 'Entrepreneur service surveys require an entrepreneur service snapshot.';
                    END IF;
                ELSIF NEW.service_activation_id IS NOT NULL OR NEW.service_snapshot IS NOT NULL THEN
                    RAISE EXCEPTION 'Only service improvement surveys may reference a service activation.';
                END IF;

                RETURN NEW;
            END;
            $$;

            DROP INDEX IF EXISTS survey_assignments_one_open_entrepreneur_service_survey;

            CREATE UNIQUE INDEX survey_assignments_one_open_entrepreneur_service_survey
                ON survey_assignments (entrepreneur_profile_id)
                WHERE entrepreneur_profile_id IS NOT NULL
                    AND service_activation_id IS NULL
                    AND service_snapshot->>'source' = 'entrepreneur_profile'
                    AND status IN ('pending', 'in_progress');
        SQL);
    }
};
