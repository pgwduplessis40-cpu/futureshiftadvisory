<?php

declare(strict_types=1);

use App\Models\BusinessPlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_plans', function (Blueprint $table): void {
            $table->timestampTz('submitted_at')->nullable()->after('status');
        });

        Schema::table('entrepreneur_profiles', function (Blueprint $table): void {
            $table->boolean('gamification_on')->default(false)->after('concept_summary');
            $table->unsignedInteger('current_streak')->default(0)->after('gamification_on');
            $table->timestampTz('last_active_at')->nullable()->after('current_streak');
        });

        Schema::create('entrepreneur_milestone_awards', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('entrepreneur_profile_id')->constrained('entrepreneur_profiles')->restrictOnDelete();
            $table->string('milestone_key', 80);
            $table->string('evidence_source_type', 80);
            $table->string('evidence_source_id', 80)->nullable();
            $table->jsonb('evidence_snapshot')->nullable();
            $table->timestampTz('earned_at');
            $table->timestampTz('seen_at')->nullable();
            $table->timestampsTz();

            $table->index(['entrepreneur_profile_id', 'earned_at']);
            $table->index(['entrepreneur_profile_id', 'milestone_key']);
            $table->index(['evidence_source_type', 'evidence_source_id']);
        });

        Schema::create('entrepreneur_streak_events', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('entrepreneur_profile_id')->constrained('entrepreneur_profiles')->restrictOnDelete();
            $table->uuid('plan_section_id')->nullable();
            $table->string('body_hash', 64);
            $table->unsignedInteger('word_count');
            $table->date('active_day');
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['entrepreneur_profile_id', 'plan_section_id', 'occurred_at']);
            $table->index(['entrepreneur_profile_id', 'active_day']);
        });

        if ($this->onPostgres()) {
            $this->backfillSubmittedAt();
            $this->installSubmittedAtGuard();
            $this->installAwardIndexes();
            $this->installAwardGuard();
            $this->installStreakGuard();
            $this->installRlsPolicies();
        }
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS business_plans_submitted_at_no_insert ON business_plans;
                DROP TRIGGER IF EXISTS business_plans_submitted_at_no_bad_update ON business_plans;
                DROP FUNCTION IF EXISTS fsa_business_plans_submitted_at_guard();

                DROP TRIGGER IF EXISTS entrepreneur_milestone_awards_guard_update ON entrepreneur_milestone_awards;
                DROP TRIGGER IF EXISTS entrepreneur_milestone_awards_guard_delete ON entrepreneur_milestone_awards;
                DROP TRIGGER IF EXISTS entrepreneur_milestone_awards_guard_truncate ON entrepreneur_milestone_awards;
                DROP FUNCTION IF EXISTS fsa_entrepreneur_milestone_awards_guard();

                DROP TRIGGER IF EXISTS entrepreneur_streak_events_no_update ON entrepreneur_streak_events;
                DROP TRIGGER IF EXISTS entrepreneur_streak_events_no_delete ON entrepreneur_streak_events;
                DROP TRIGGER IF EXISTS entrepreneur_streak_events_no_truncate ON entrepreneur_streak_events;
                DROP FUNCTION IF EXISTS fsa_entrepreneur_streak_events_block_mutation();
            SQL);
        }

        Schema::dropIfExists('entrepreneur_streak_events');
        Schema::dropIfExists('entrepreneur_milestone_awards');

        Schema::table('entrepreneur_profiles', function (Blueprint $table): void {
            $table->dropColumn(['gamification_on', 'current_streak', 'last_active_at']);
        });

        Schema::table('business_plans', function (Blueprint $table): void {
            $table->dropColumn('submitted_at');
        });
    }

    private function backfillSubmittedAt(): void
    {
        $morphClass = (new BusinessPlan)->getMorphClass();

        DB::statement(<<<'SQL'
            UPDATE business_plans bp
            SET submitted_at = (
                SELECT min(ae.occurred_at)
                FROM audit_events ae
                WHERE ae.action = 'entrepreneur.plan_submitted'
                    AND ae.subject_type = ?
                    AND ae.subject_id = bp.id::text
            )
            WHERE bp.submitted_at IS NULL
        SQL, [$morphClass]);
    }

    private function installSubmittedAtGuard(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fsa_business_plans_submitted_at_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    IF NEW.submitted_at IS NOT NULL THEN
                        RAISE EXCEPTION 'business_plans.submitted_at must be set by the submit transition, not INSERT'
                            USING ERRCODE = 'P0001';
                    END IF;

                    RETURN NEW;
                END IF;

                IF OLD.submitted_at IS NOT NULL AND NEW.submitted_at IS DISTINCT FROM OLD.submitted_at THEN
                    RAISE EXCEPTION 'business_plans.submitted_at is immutable once set'
                        USING ERRCODE = 'P0001';
                END IF;

                IF NEW.submitted_at IS NOT NULL
                    AND OLD.submitted_at IS NULL
                    AND NOT (
                        OLD.status IN ('draft', 'building', 'ready')
                        AND NEW.status = 'submitted'
                    )
                THEN
                    RAISE EXCEPTION 'business_plans.submitted_at can only be first set on the draft/building/ready to submitted transition'
                        USING ERRCODE = 'P0001';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER business_plans_submitted_at_no_insert
                BEFORE INSERT ON business_plans
                FOR EACH ROW
                EXECUTE FUNCTION fsa_business_plans_submitted_at_guard();

            CREATE TRIGGER business_plans_submitted_at_no_bad_update
                BEFORE UPDATE ON business_plans
                FOR EACH ROW
                EXECUTE FUNCTION fsa_business_plans_submitted_at_guard();
        SQL);
    }

    private function installAwardIndexes(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE UNIQUE INDEX entrepreneur_milestone_awards_singleton_unique
                ON entrepreneur_milestone_awards (entrepreneur_profile_id, milestone_key)
                WHERE milestone_key <> 'grade_up';

            CREATE UNIQUE INDEX entrepreneur_milestone_awards_grade_up_unique
                ON entrepreneur_milestone_awards (entrepreneur_profile_id, milestone_key, evidence_source_id)
                WHERE milestone_key = 'grade_up';
        SQL);
    }

    private function installAwardGuard(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fsa_entrepreneur_milestone_awards_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF OLD.id IS DISTINCT FROM NEW.id
                        OR OLD.entrepreneur_profile_id IS DISTINCT FROM NEW.entrepreneur_profile_id
                        OR OLD.milestone_key IS DISTINCT FROM NEW.milestone_key
                        OR OLD.evidence_source_type IS DISTINCT FROM NEW.evidence_source_type
                        OR OLD.evidence_source_id IS DISTINCT FROM NEW.evidence_source_id
                        OR OLD.evidence_snapshot IS DISTINCT FROM NEW.evidence_snapshot
                        OR OLD.earned_at IS DISTINCT FROM NEW.earned_at
                        OR OLD.created_at IS DISTINCT FROM NEW.created_at
                    THEN
                        RAISE EXCEPTION 'entrepreneur_milestone_awards are immutable except seen_at'
                            USING ERRCODE = 'P0001';
                    END IF;

                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'entrepreneur_milestone_awards are insert-only; % is forbidden', TG_OP
                    USING ERRCODE = 'P0001';
            END;
            $$;

            CREATE TRIGGER entrepreneur_milestone_awards_guard_update
                BEFORE UPDATE ON entrepreneur_milestone_awards
                FOR EACH ROW
                EXECUTE FUNCTION fsa_entrepreneur_milestone_awards_guard();

            CREATE TRIGGER entrepreneur_milestone_awards_guard_delete
                BEFORE DELETE ON entrepreneur_milestone_awards
                FOR EACH ROW
                EXECUTE FUNCTION fsa_entrepreneur_milestone_awards_guard();

            CREATE TRIGGER entrepreneur_milestone_awards_guard_truncate
                BEFORE TRUNCATE ON entrepreneur_milestone_awards
                FOR EACH STATEMENT
                EXECUTE FUNCTION fsa_entrepreneur_milestone_awards_guard();
        SQL);
    }

    private function installStreakGuard(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fsa_entrepreneur_streak_events_block_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'entrepreneur_streak_events is append-only; % is forbidden', TG_OP
                    USING ERRCODE = 'P0001';
            END;
            $$;

            CREATE TRIGGER entrepreneur_streak_events_no_update
                BEFORE UPDATE ON entrepreneur_streak_events
                FOR EACH ROW
                EXECUTE FUNCTION fsa_entrepreneur_streak_events_block_mutation();

            CREATE TRIGGER entrepreneur_streak_events_no_delete
                BEFORE DELETE ON entrepreneur_streak_events
                FOR EACH ROW
                EXECUTE FUNCTION fsa_entrepreneur_streak_events_block_mutation();

            CREATE TRIGGER entrepreneur_streak_events_no_truncate
                BEFORE TRUNCATE ON entrepreneur_streak_events
                FOR EACH STATEMENT
                EXECUTE FUNCTION fsa_entrepreneur_streak_events_block_mutation();
        SQL);
    }

    private function installRlsPolicies(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE entrepreneur_milestone_awards ENABLE ROW LEVEL SECURITY;
            ALTER TABLE entrepreneur_milestone_awards FORCE ROW LEVEL SECURITY;

            CREATE POLICY entrepreneur_milestone_awards_select ON entrepreneur_milestone_awards
                FOR SELECT
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = entrepreneur_milestone_awards.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            CREATE POLICY entrepreneur_milestone_awards_insert_system ON entrepreneur_milestone_awards
                FOR INSERT
                WITH CHECK (fsa_current_role() = 'system');

            CREATE POLICY entrepreneur_milestone_awards_update_seen ON entrepreneur_milestone_awards
                FOR UPDATE
                USING (
                    EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = entrepreneur_milestone_awards.entrepreneur_profile_id
                        AND entrepreneur_profiles.user_id::text = fsa_current_user_id()
                    )
                )
                WITH CHECK (
                    EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = entrepreneur_milestone_awards.entrepreneur_profile_id
                        AND entrepreneur_profiles.user_id::text = fsa_current_user_id()
                    )
                );

            ALTER TABLE entrepreneur_streak_events ENABLE ROW LEVEL SECURITY;
            ALTER TABLE entrepreneur_streak_events FORCE ROW LEVEL SECURITY;

            CREATE POLICY entrepreneur_streak_events_select ON entrepreneur_streak_events
                FOR SELECT
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = entrepreneur_streak_events.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            CREATE POLICY entrepreneur_streak_events_insert_system ON entrepreneur_streak_events
                FOR INSERT
                WITH CHECK (fsa_current_role() = 'system');
        SQL);
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
