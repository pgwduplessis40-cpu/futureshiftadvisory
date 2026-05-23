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
        Schema::create('bulk_communications', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('title', 160);
            $table->string('template_key', 80)->nullable();
            $table->string('subject', 160);
            $table->text('body');
            $table->string('audience_type', 40);
            $table->jsonb('selected_client_ids')->nullable();
            $table->string('status', 40)->default('scheduled');
            $table->timestampTz('scheduled_at');
            $table->timestampTz('sent_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metrics')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'scheduled_at']);
            $table->index('created_by_user_id');
            $table->index('template_key');
        });

        Schema::create('bulk_communication_recipients', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('bulk_communication_id')->constrained('bulk_communications')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('channel', 40);
            $table->string('preference_channel', 40);
            $table->string('preference_frequency', 40);
            $table->string('status', 40)->default('pending');
            $table->string('skipped_reason', 80)->nullable();
            $table->string('open_token', 80)->nullable()->unique();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->jsonb('delivery_metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['bulk_communication_id', 'client_id', 'user_id'], 'bulk_comm_recipient_unique');
            $table->index(['client_id', 'sent_at']);
            $table->index(['bulk_communication_id', 'status']);
            $table->index(['opened_at']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_communication_recipients');
        Schema::dropIfExists('bulk_communications');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE bulk_communications ENABLE ROW LEVEL SECURITY;
            ALTER TABLE bulk_communications FORCE ROW LEVEL SECURITY;

            CREATE POLICY bulk_communications_owner_scope ON bulk_communications
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR created_by_user_id::text = fsa_current_user_id()
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR created_by_user_id::text = fsa_current_user_id()
                );

            ALTER TABLE bulk_communication_recipients ENABLE ROW LEVEL SECURITY;
            ALTER TABLE bulk_communication_recipients FORCE ROW LEVEL SECURITY;

            CREATE POLICY bulk_communication_recipients_client_scope ON bulk_communication_recipients
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
