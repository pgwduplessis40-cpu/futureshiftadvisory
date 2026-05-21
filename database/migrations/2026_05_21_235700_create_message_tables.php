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
        Schema::create('message_threads', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('entrepreneur_profile_id')->nullable()->constrained('entrepreneur_profiles')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 160);
            $table->timestampTz('last_activity_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'last_activity_at']);
            $table->index('entrepreneur_profile_id');
            $table->index('created_by_user_id');
        });

        Schema::create('message_thread_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampsTz();

            $table->unique(['thread_id', 'user_id']);
            $table->index(['user_id', 'last_read_at']);
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->jsonb('attachments')->nullable();
            $table->timestampTz('sent_at');
            $table->timestampsTz();

            $table->index(['thread_id', 'sent_at']);
            $table->index('sender_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_thread_participants');
        Schema::dropIfExists('message_threads');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE message_threads ENABLE ROW LEVEL SECURITY;
            ALTER TABLE message_threads FORCE ROW LEVEL SECURITY;

            CREATE POLICY message_threads_client_scope ON message_threads
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                );

            ALTER TABLE message_thread_participants ENABLE ROW LEVEL SECURITY;
            ALTER TABLE message_thread_participants FORCE ROW LEVEL SECURITY;

            CREATE POLICY message_thread_participants_scope ON message_thread_participants
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR user_id::text = fsa_current_user_id()
                    OR EXISTS (
                        SELECT 1
                        FROM message_threads
                        WHERE message_threads.id = message_thread_participants.thread_id
                            AND message_threads.client_id IS NOT NULL
                            AND message_threads.client_id::text = ANY (fsa_current_client_ids())
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR user_id::text = fsa_current_user_id()
                    OR EXISTS (
                        SELECT 1
                        FROM message_threads
                        WHERE message_threads.id = message_thread_participants.thread_id
                            AND message_threads.client_id IS NOT NULL
                            AND message_threads.client_id::text = ANY (fsa_current_client_ids())
                    )
                );

            ALTER TABLE messages ENABLE ROW LEVEL SECURITY;
            ALTER TABLE messages FORCE ROW LEVEL SECURITY;

            CREATE POLICY messages_client_scope ON messages
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM message_threads
                        WHERE message_threads.id = messages.thread_id
                            AND message_threads.client_id IS NOT NULL
                            AND message_threads.client_id::text = ANY (fsa_current_client_ids())
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM message_threads
                        WHERE message_threads.id = messages.thread_id
                            AND message_threads.client_id IS NOT NULL
                            AND message_threads.client_id::text = ANY (fsa_current_client_ids())
                    )
                );
        SQL);
    }
};
