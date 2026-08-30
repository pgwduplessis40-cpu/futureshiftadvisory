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
        Schema::create('service_journey_enrollments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('client_id');
            $table->foreignId('participant_user_id');
            $table->string('service_key', 80);
            $table->string('program_version', 40);
            $table->boolean('recognition_enabled')->default(false);
            $table->string('timezone', 64)->default('Pacific/Auckland');
            $table->timestampTz('recognition_enabled_at')->nullable();
            $table->timestampTz('recognition_disabled_at')->nullable();
            $table->timestampsTz();

            $table->foreign('client_id', 'service_journey_enrollments_client_fk')->references('id')->on('clients')->restrictOnDelete();
            $table->foreign('participant_user_id', 'service_journey_enrollments_user_fk')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['client_id', 'participant_user_id', 'service_key'], 'service_journey_enrollment_user_service_unique');
            $table->index(['client_id', 'recognition_enabled'], 'service_journey_enrollment_client_enabled_index');
        });

        Schema::create('service_journey_milestone_awards', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('service_journey_enrollment_id');
            $table->string('milestone_key', 80);
            $table->string('evidence_source_type', 80);
            $table->string('evidence_source_id', 120);
            $table->jsonb('evidence_snapshot')->nullable();
            $table->timestampTz('earned_at');
            $table->timestampTz('seen_at')->nullable();
            $table->timestampsTz();

            $table->foreign('service_journey_enrollment_id', 'service_journey_awards_enrollment_fk')->references('id')->on('service_journey_enrollments')->restrictOnDelete();
            $table->unique(['service_journey_enrollment_id', 'milestone_key'], 'service_journey_awards_enrollment_milestone_unique');
            $table->index(['service_journey_enrollment_id', 'earned_at'], 'service_journey_awards_enrollment_earned_index');
        });

        Schema::create('service_journey_point_events', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('service_journey_enrollment_id');
            $table->uuid('service_journey_milestone_award_id');
            $table->string('milestone_key', 80);
            $table->integer('points');
            $table->timestampTz('earned_at');
            $table->timestampsTz();

            $table->foreign('service_journey_enrollment_id', 'service_journey_points_enrollment_fk')->references('id')->on('service_journey_enrollments')->restrictOnDelete();
            $table->foreign('service_journey_milestone_award_id', 'service_journey_points_award_fk')->references('id')->on('service_journey_milestone_awards')->restrictOnDelete();
            $table->unique('service_journey_milestone_award_id', 'service_journey_points_award_unique');
            $table->index(['service_journey_enrollment_id', 'earned_at'], 'service_journey_points_enrollment_earned_index');
        });

        if ($this->onPostgres()) {
            $this->installGuards();
            $this->installRlsPolicies();
        }
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS service_journey_awards_guard_update ON service_journey_milestone_awards;
                DROP TRIGGER IF EXISTS service_journey_awards_guard_delete ON service_journey_milestone_awards;
                DROP TRIGGER IF EXISTS service_journey_awards_guard_truncate ON service_journey_milestone_awards;
                DROP FUNCTION IF EXISTS fsa_service_journey_awards_guard();

                DROP TRIGGER IF EXISTS service_journey_points_guard_update ON service_journey_point_events;
                DROP TRIGGER IF EXISTS service_journey_points_guard_delete ON service_journey_point_events;
                DROP TRIGGER IF EXISTS service_journey_points_guard_truncate ON service_journey_point_events;
                DROP FUNCTION IF EXISTS fsa_service_journey_points_guard();
            SQL);
        }

        Schema::dropIfExists('service_journey_point_events');
        Schema::dropIfExists('service_journey_milestone_awards');
        Schema::dropIfExists('service_journey_enrollments');
    }

    private function installGuards(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fsa_service_journey_awards_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF OLD.id IS DISTINCT FROM NEW.id
                        OR OLD.service_journey_enrollment_id IS DISTINCT FROM NEW.service_journey_enrollment_id
                        OR OLD.milestone_key IS DISTINCT FROM NEW.milestone_key
                        OR OLD.evidence_source_type IS DISTINCT FROM NEW.evidence_source_type
                        OR OLD.evidence_source_id IS DISTINCT FROM NEW.evidence_source_id
                        OR OLD.evidence_snapshot IS DISTINCT FROM NEW.evidence_snapshot
                        OR OLD.earned_at IS DISTINCT FROM NEW.earned_at
                        OR OLD.created_at IS DISTINCT FROM NEW.created_at
                        OR OLD.updated_at IS DISTINCT FROM NEW.updated_at
                    THEN
                        RAISE EXCEPTION 'service_journey_milestone_awards are immutable except seen_at'
                            USING ERRCODE = 'P0001';
                    END IF;

                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'service_journey_milestone_awards are insert-only; % is forbidden', TG_OP
                    USING ERRCODE = 'P0001';
            END;
            $$;

            CREATE TRIGGER service_journey_awards_guard_update
                BEFORE UPDATE ON service_journey_milestone_awards
                FOR EACH ROW EXECUTE FUNCTION fsa_service_journey_awards_guard();
            CREATE TRIGGER service_journey_awards_guard_delete
                BEFORE DELETE ON service_journey_milestone_awards
                FOR EACH ROW EXECUTE FUNCTION fsa_service_journey_awards_guard();
            CREATE TRIGGER service_journey_awards_guard_truncate
                BEFORE TRUNCATE ON service_journey_milestone_awards
                FOR EACH STATEMENT EXECUTE FUNCTION fsa_service_journey_awards_guard();

            CREATE OR REPLACE FUNCTION fsa_service_journey_points_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'service_journey_point_events are append-only; % is forbidden', TG_OP
                    USING ERRCODE = 'P0001';
            END;
            $$;

            CREATE TRIGGER service_journey_points_guard_update
                BEFORE UPDATE ON service_journey_point_events
                FOR EACH ROW EXECUTE FUNCTION fsa_service_journey_points_guard();
            CREATE TRIGGER service_journey_points_guard_delete
                BEFORE DELETE ON service_journey_point_events
                FOR EACH ROW EXECUTE FUNCTION fsa_service_journey_points_guard();
            CREATE TRIGGER service_journey_points_guard_truncate
                BEFORE TRUNCATE ON service_journey_point_events
                FOR EACH STATEMENT EXECUTE FUNCTION fsa_service_journey_points_guard();
        SQL);
    }

    private function installRlsPolicies(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE service_journey_enrollments ENABLE ROW LEVEL SECURITY;
            ALTER TABLE service_journey_enrollments FORCE ROW LEVEL SECURITY;
            CREATE POLICY service_journey_enrollments_select ON service_journey_enrollments
                FOR SELECT USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );
            CREATE POLICY service_journey_enrollments_system_write ON service_journey_enrollments
                FOR ALL USING (fsa_current_role() = 'system')
                WITH CHECK (fsa_current_role() = 'system');

            ALTER TABLE service_journey_milestone_awards ENABLE ROW LEVEL SECURITY;
            ALTER TABLE service_journey_milestone_awards FORCE ROW LEVEL SECURITY;
            CREATE POLICY service_journey_awards_select ON service_journey_milestone_awards
                FOR SELECT USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1 FROM service_journey_enrollments
                        WHERE service_journey_enrollments.id = service_journey_milestone_awards.service_journey_enrollment_id
                        AND service_journey_enrollments.client_id::text = ANY (fsa_current_client_ids())
                    )
                );
            CREATE POLICY service_journey_awards_system_write ON service_journey_milestone_awards
                FOR ALL USING (fsa_current_role() = 'system')
                WITH CHECK (fsa_current_role() = 'system');

            ALTER TABLE service_journey_point_events ENABLE ROW LEVEL SECURITY;
            ALTER TABLE service_journey_point_events FORCE ROW LEVEL SECURITY;
            CREATE POLICY service_journey_points_select ON service_journey_point_events
                FOR SELECT USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1 FROM service_journey_enrollments
                        WHERE service_journey_enrollments.id = service_journey_point_events.service_journey_enrollment_id
                        AND service_journey_enrollments.client_id::text = ANY (fsa_current_client_ids())
                    )
                );
            CREATE POLICY service_journey_points_system_write ON service_journey_point_events
                FOR ALL USING (fsa_current_role() = 'system')
                WITH CHECK (fsa_current_role() = 'system');
        SQL);
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
