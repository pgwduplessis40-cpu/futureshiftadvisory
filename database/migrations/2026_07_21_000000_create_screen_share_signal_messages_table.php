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
        Schema::create('screen_share_signal_messages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('session_id')->constrained('screen_share_sessions')->cascadeOnDelete();
            $table->foreignUuid('recipient_connection_id')->constrained('screen_share_connections')->cascadeOnDelete();
            $table->string('type', 16);
            $table->jsonb('payload');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(
                ['session_id', 'recipient_connection_id', 'id'],
                'screen_share_signal_messages_pending_idx',
            );
        });

        if ($this->onPostgres()) {
            DB::unprepared(<<<'SQL'
                ALTER TABLE screen_share_signal_messages ENABLE ROW LEVEL SECURITY;
                ALTER TABLE screen_share_signal_messages FORCE ROW LEVEL SECURITY;

                CREATE POLICY screen_share_signal_messages_scope ON screen_share_signal_messages
                    USING (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR EXISTS (
                            SELECT 1
                            FROM screen_share_sessions
                            WHERE screen_share_sessions.id = screen_share_signal_messages.session_id
                                AND (
                                    screen_share_sessions.client_user_id::text = fsa_current_user_id()
                                    OR screen_share_sessions.advisor_id::text = fsa_current_user_id()
                                )
                        )
                    )
                    WITH CHECK (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR EXISTS (
                            SELECT 1
                            FROM screen_share_sessions
                            WHERE screen_share_sessions.id = screen_share_signal_messages.session_id
                                AND (
                                    screen_share_sessions.client_user_id::text = fsa_current_user_id()
                                    OR screen_share_sessions.advisor_id::text = fsa_current_user_id()
                                )
                        )
                    );
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_share_signal_messages');
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
