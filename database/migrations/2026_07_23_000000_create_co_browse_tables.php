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
        Schema::create('co_browse_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('entrepreneur_profile_id')->nullable()->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('participant_type', 16);
            $table->string('context_key', 120);
            $table->string('secret_hash', 64);
            $table->timestampTz('last_seen_at');
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->index(['client_id', 'user_id', 'participant_type', 'expires_at'], 'co_browse_connections_client_presence_idx');
            $table->index(['entrepreneur_profile_id', 'user_id', 'participant_type', 'expires_at'], 'co_browse_connections_entrepreneur_presence_idx');
        });

        Schema::create('co_browse_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('entrepreneur_profile_id')->nullable()->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('client_connection_id')->nullable()->constrained('co_browse_connections')->restrictOnDelete();
            $table->foreignUuid('advisor_connection_id')->constrained('co_browse_connections')->restrictOnDelete();
            $table->string('status', 20);
            $table->string('client_response', 16)->nullable();
            $table->timestampTz('client_response_at')->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('session_started_at')->nullable();
            $table->timestampTz('session_ended_at')->nullable();
            $table->string('end_reason', 48)->nullable();
            $table->timestampTz('last_heartbeat_at')->nullable();
            $table->timestampTz('expires_at');
            $table->jsonb('consent_context')->nullable();
            $table->jsonb('authorization_basis');
            $table->jsonb('prompted_connections');
            $table->unsignedInteger('actions_count')->default(0);
            $table->timestampsTz();

            $table->index(['client_id', 'status', 'expires_at'], 'co_browse_sessions_client_scope_idx');
            $table->index(['entrepreneur_profile_id', 'status', 'expires_at'], 'co_browse_sessions_entrepreneur_scope_idx');
        });

        Schema::create('co_browse_actions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('session_id')->constrained('co_browse_sessions')->cascadeOnDelete();
            $table->foreignUuid('recipient_connection_id')->constrained('co_browse_connections')->cascadeOnDelete();
            $table->foreignUuid('sender_connection_id')->constrained('co_browse_connections')->cascadeOnDelete();
            $table->string('type', 32);
            $table->jsonb('payload');
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->index(['session_id', 'recipient_connection_id', 'id'], 'co_browse_actions_pending_idx');
        });

        DB::statement("CREATE UNIQUE INDEX co_browse_one_open_client_user ON co_browse_sessions (client_user_id) WHERE status <> 'ended'");
        DB::statement("CREATE UNIQUE INDEX co_browse_one_open_advisor ON co_browse_sessions (advisor_id) WHERE status <> 'ended'");

        if ($this->onPostgres()) {
            DB::unprepared(<<<'SQL'
                ALTER TABLE co_browse_connections
                ADD CONSTRAINT co_browse_connections_one_subject
                CHECK (
                    (client_id IS NOT NULL AND entrepreneur_profile_id IS NULL)
                    OR (client_id IS NULL AND entrepreneur_profile_id IS NOT NULL)
                );

                ALTER TABLE co_browse_sessions
                ADD CONSTRAINT co_browse_sessions_one_subject
                CHECK (
                    (client_id IS NOT NULL AND entrepreneur_profile_id IS NULL)
                    OR (client_id IS NULL AND entrepreneur_profile_id IS NOT NULL)
                );

                ALTER TABLE co_browse_connections ENABLE ROW LEVEL SECURITY;
                ALTER TABLE co_browse_connections FORCE ROW LEVEL SECURITY;
                CREATE POLICY co_browse_connections_scope ON co_browse_connections
                    USING (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR user_id::text = fsa_current_user_id()
                    )
                    WITH CHECK (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR user_id::text = fsa_current_user_id()
                    );

                ALTER TABLE co_browse_sessions ENABLE ROW LEVEL SECURITY;
                ALTER TABLE co_browse_sessions FORCE ROW LEVEL SECURITY;
                CREATE POLICY co_browse_sessions_scope ON co_browse_sessions
                    USING (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR client_user_id::text = fsa_current_user_id()
                        OR advisor_id::text = fsa_current_user_id()
                    )
                    WITH CHECK (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR client_user_id::text = fsa_current_user_id()
                        OR advisor_id::text = fsa_current_user_id()
                    );

                ALTER TABLE co_browse_actions ENABLE ROW LEVEL SECURITY;
                ALTER TABLE co_browse_actions FORCE ROW LEVEL SECURITY;
                CREATE POLICY co_browse_actions_scope ON co_browse_actions
                    USING (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR EXISTS (
                            SELECT 1 FROM co_browse_sessions
                            WHERE co_browse_sessions.id = co_browse_actions.session_id
                            AND (
                                co_browse_sessions.client_user_id::text = fsa_current_user_id()
                                OR co_browse_sessions.advisor_id::text = fsa_current_user_id()
                            )
                        )
                    )
                    WITH CHECK (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR EXISTS (
                            SELECT 1 FROM co_browse_sessions
                            WHERE co_browse_sessions.id = co_browse_actions.session_id
                            AND (
                                co_browse_sessions.client_user_id::text = fsa_current_user_id()
                                OR co_browse_sessions.advisor_id::text = fsa_current_user_id()
                            )
                        )
                    );
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('co_browse_actions');
        Schema::dropIfExists('co_browse_sessions');
        Schema::dropIfExists('co_browse_connections');
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
