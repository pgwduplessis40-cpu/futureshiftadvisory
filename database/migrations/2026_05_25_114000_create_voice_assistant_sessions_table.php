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
        Schema::create('voice_assistant_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('advisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('call_log_id')->nullable()->constrained('call_logs')->nullOnDelete();
            $table->string('status', 24)->default('started');
            $table->string('shortcut_intent', 80);
            $table->jsonb('shortcut_payload');
            $table->text('transcript')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status', 'started_at']);
            $table->index('call_log_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE voice_assistant_sessions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE voice_assistant_sessions FORCE ROW LEVEL SECURITY;
            CREATE POLICY voice_assistant_sessions_client_scope ON voice_assistant_sessions
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_assistant_sessions');
    }
};
