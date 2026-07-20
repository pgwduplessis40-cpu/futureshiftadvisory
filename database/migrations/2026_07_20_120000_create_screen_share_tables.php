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
        Schema::create('screen_share_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('participant_type', 16);
            $table->string('context_key', 120);
            $table->string('secret_hash', 64);
            $table->timestampTz('last_seen_at');
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->index(['client_id', 'user_id', 'participant_type', 'expires_at'], 'screen_share_connections_presence_idx');
            $table->index(['user_id', 'expires_at']);
        });

        Schema::create('screen_share_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('client_connection_id')->nullable()->constrained('screen_share_connections')->restrictOnDelete();
            $table->foreignUuid('advisor_connection_id')->constrained('screen_share_connections')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('client_response', 16)->nullable();
            $table->timestampTz('client_response_at')->nullable();
            $table->boolean('browser_permission_granted')->default(false);
            $table->timestampTz('requested_at');
            $table->timestampTz('picker_deadline_at')->nullable();
            $table->timestampTz('session_started_at')->nullable();
            $table->timestampTz('session_ended_at')->nullable();
            $table->string('end_reason', 48)->nullable();
            $table->string('connection_type', 16)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('display_surface', 16)->nullable();
            $table->timestampTz('last_heartbeat_at')->nullable();
            $table->timestampTz('expires_at');
            $table->jsonb('consent_context')->nullable();
            $table->jsonb('authorization_basis');
            $table->jsonb('prompted_connections');
            $table->timestampsTz();

            $table->index(['client_id', 'status', 'expires_at']);
            $table->index(['client_user_id', 'status']);
            $table->index(['advisor_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX screen_share_one_open_client_user ON screen_share_sessions (client_user_id) WHERE status <> 'ended'");
        DB::statement("CREATE UNIQUE INDEX screen_share_one_open_advisor ON screen_share_sessions (advisor_id) WHERE status <> 'ended'");

        if ($this->onPostgres()) {
            DB::unprepared(<<<'SQL'
                ALTER TABLE screen_share_connections ENABLE ROW LEVEL SECURITY;
                ALTER TABLE screen_share_connections FORCE ROW LEVEL SECURITY;

                CREATE POLICY screen_share_connections_scope ON screen_share_connections
                    USING (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR user_id::text = fsa_current_user_id()
                    )
                    WITH CHECK (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR user_id::text = fsa_current_user_id()
                    );

                ALTER TABLE screen_share_sessions ENABLE ROW LEVEL SECURITY;
                ALTER TABLE screen_share_sessions FORCE ROW LEVEL SECURITY;

                CREATE POLICY screen_share_sessions_scope ON screen_share_sessions
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
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_share_sessions');
        Schema::dropIfExists('screen_share_connections');
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
