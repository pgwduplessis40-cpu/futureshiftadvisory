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
        Schema::create('voice_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('transcription_text')->nullable();
            $table->jsonb('transcription_metadata')->nullable();
            $table->text('summary_text')->nullable();
            $table->jsonb('summary_payload')->nullable();
            $table->string('status', 32)->default('uploaded');
            $table->timestampTz('transcribed_at')->nullable();
            $table->timestampTz('summarized_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index('document_id');
        });

        Schema::create('call_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('voice_note_id')->nullable()->constrained('voice_notes')->nullOnDelete();
            $table->foreignId('advisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('channel', 32)->default('phone_call');
            $table->timestampTz('occurred_at');
            $table->text('transcript')->nullable();
            $table->text('summary')->nullable();
            $table->jsonb('action_items')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'occurred_at']);
            $table->index('voice_note_id');
        });

        Schema::table('milestone_actions', function (Blueprint $table): void {
            $table->foreignUuid('call_log_id')
                ->nullable()
                ->after('client_id')
                ->constrained('call_logs')
                ->nullOnDelete();

            $table->index('call_log_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::table('milestone_actions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('call_log_id');
        });

        Schema::dropIfExists('call_logs');
        Schema::dropIfExists('voice_notes');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['voice_notes', 'call_logs'] as $table) {
            DB::unprepared(<<<SQL
                ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;
                ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;

                CREATE POLICY {$table}_client_scope ON {$table}
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
    }
};
