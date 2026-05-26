<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->installEntrepreneurAwarePolicies();
    }

    public function down(): void
    {
        $this->installClientOnlyPolicies();
    }

    private function installEntrepreneurAwarePolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS message_threads_client_scope ON message_threads;
            DROP POLICY IF EXISTS message_threads_scope ON message_threads;
            CREATE POLICY message_threads_scope ON message_threads
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = message_threads.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = message_threads.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            DROP POLICY IF EXISTS message_thread_participants_scope ON message_thread_participants;
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
                    OR EXISTS (
                        SELECT 1
                        FROM message_threads
                        JOIN entrepreneur_profiles ON entrepreneur_profiles.id = message_threads.entrepreneur_profile_id
                        WHERE message_threads.id = message_thread_participants.thread_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
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
                    OR EXISTS (
                        SELECT 1
                        FROM message_threads
                        JOIN entrepreneur_profiles ON entrepreneur_profiles.id = message_threads.entrepreneur_profile_id
                        WHERE message_threads.id = message_thread_participants.thread_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            DROP POLICY IF EXISTS messages_client_scope ON messages;
            DROP POLICY IF EXISTS messages_scope ON messages;
            CREATE POLICY messages_scope ON messages
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM message_threads
                        WHERE message_threads.id = messages.thread_id
                            AND message_threads.client_id IS NOT NULL
                            AND message_threads.client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM message_threads
                        JOIN entrepreneur_profiles ON entrepreneur_profiles.id = message_threads.entrepreneur_profile_id
                        WHERE message_threads.id = messages.thread_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
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
                    OR EXISTS (
                        SELECT 1
                        FROM message_threads
                        JOIN entrepreneur_profiles ON entrepreneur_profiles.id = message_threads.entrepreneur_profile_id
                        WHERE message_threads.id = messages.thread_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );
        SQL);
    }

    private function installClientOnlyPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS message_threads_scope ON message_threads;
            DROP POLICY IF EXISTS message_threads_client_scope ON message_threads;
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

            DROP POLICY IF EXISTS message_thread_participants_scope ON message_thread_participants;
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

            DROP POLICY IF EXISTS messages_scope ON messages;
            DROP POLICY IF EXISTS messages_client_scope ON messages;
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
