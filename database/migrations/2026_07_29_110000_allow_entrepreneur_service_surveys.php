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
                        OR NEW.service_snapshot->>'source' <> 'entrepreneur_profile' THEN
                        RAISE EXCEPTION 'Entrepreneur service surveys require an entrepreneur service snapshot.';
                    END IF;
                ELSIF NEW.service_activation_id IS NOT NULL OR NEW.service_snapshot IS NOT NULL THEN
                    RAISE EXCEPTION 'Only service improvement surveys may reference a service activation.';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE UNIQUE INDEX survey_assignments_one_open_entrepreneur_service_survey
                ON survey_assignments (entrepreneur_profile_id)
                WHERE entrepreneur_profile_id IS NOT NULL
                    AND service_activation_id IS NULL
                    AND service_snapshot->>'source' = 'entrepreneur_profile'
                    AND status IN ('pending', 'in_progress');
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS survey_assignments_one_open_entrepreneur_service_survey;

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
        SQL);
    }
};
