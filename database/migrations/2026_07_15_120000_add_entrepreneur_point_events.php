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
        Schema::create('entrepreneur_point_events', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('entrepreneur_profile_id')->constrained('entrepreneur_profiles')->restrictOnDelete();
            $table->foreignUuid('milestone_award_id')->unique()->constrained('entrepreneur_milestone_awards')->restrictOnDelete();
            $table->string('milestone_key', 80);
            $table->unsignedInteger('points');
            $table->timestampTz('earned_at');
            $table->timestampsTz();
        });

        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fsa_entrepreneur_point_events_block_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'entrepreneur_point_events are insert-only; % is forbidden', TG_OP
                    USING ERRCODE = 'P0001';
            END;
            $$;

            CREATE TRIGGER entrepreneur_point_events_no_update
                BEFORE UPDATE ON entrepreneur_point_events
                FOR EACH ROW
                EXECUTE FUNCTION fsa_entrepreneur_point_events_block_mutation();

            CREATE TRIGGER entrepreneur_point_events_no_delete
                BEFORE DELETE ON entrepreneur_point_events
                FOR EACH ROW
                EXECUTE FUNCTION fsa_entrepreneur_point_events_block_mutation();

            CREATE TRIGGER entrepreneur_point_events_no_truncate
                BEFORE TRUNCATE ON entrepreneur_point_events
                FOR EACH STATEMENT
                EXECUTE FUNCTION fsa_entrepreneur_point_events_block_mutation();

            ALTER TABLE entrepreneur_point_events ENABLE ROW LEVEL SECURITY;
            ALTER TABLE entrepreneur_point_events FORCE ROW LEVEL SECURITY;

            CREATE POLICY entrepreneur_point_events_select ON entrepreneur_point_events
                FOR SELECT
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = entrepreneur_point_events.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            CREATE POLICY entrepreneur_point_events_insert_system ON entrepreneur_point_events
                FOR INSERT
                WITH CHECK (fsa_current_role() = 'system');
        SQL);
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS entrepreneur_point_events_no_update ON entrepreneur_point_events;
                DROP TRIGGER IF EXISTS entrepreneur_point_events_no_delete ON entrepreneur_point_events;
                DROP TRIGGER IF EXISTS entrepreneur_point_events_no_truncate ON entrepreneur_point_events;
                DROP FUNCTION IF EXISTS fsa_entrepreneur_point_events_block_mutation();
            SQL);
        }

        Schema::dropIfExists('entrepreneur_point_events');
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
