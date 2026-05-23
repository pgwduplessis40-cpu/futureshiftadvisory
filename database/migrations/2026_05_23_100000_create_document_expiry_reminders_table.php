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
        Schema::create('document_expiry_reminders', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reminder_type', 40)->default('expires_soon');
            $table->timestampTz('expires_at_snapshot');
            $table->timestampTz('triggered_at');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['document_id', 'user_id', 'reminder_type'], 'document_expiry_reminder_unique');
            $table->index(['client_id', 'triggered_at']);
            $table->index(['expires_at_snapshot']);
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('document_expiry_reminders');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE document_expiry_reminders ENABLE ROW LEVEL SECURITY;
            ALTER TABLE document_expiry_reminders FORCE ROW LEVEL SECURITY;

            CREATE POLICY document_expiry_reminders_client_scope ON document_expiry_reminders
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
};
