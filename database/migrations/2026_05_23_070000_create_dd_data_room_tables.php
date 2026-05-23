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
        Schema::create('dd_guest_links', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('dd_engagement_id')->constrained('dd_engagements')->cascadeOnDelete();
            $table->string('workstream', 80);
            $table->string('folder', 160)->default('general');
            $table->char('token_hash', 64)->unique();
            $table->string('guest_email')->nullable();
            $table->unsignedInteger('max_uploads')->nullable();
            $table->unsignedInteger('upload_count')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'workstream']);
            $table->index(['dd_engagement_id', 'revoked_at']);
            $table->index('created_by_user_id');
            $table->index('revoked_by_user_id');
        });

        Schema::create('dd_data_room_items', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('dd_engagement_id')->constrained('dd_engagements')->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('workstream', 80);
            $table->string('folder', 160)->default('general');
            $table->string('artifact_type', 80)->default('dd_artifact');
            $table->string('source', 40);
            $table->foreignUuid('dd_guest_link_id')->nullable()->constrained('dd_guest_links')->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'workstream']);
            $table->index(['dd_engagement_id', 'workstream']);
            $table->index('document_id');
            $table->index('dd_guest_link_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('dd_data_room_items');
        Schema::dropIfExists('dd_guest_links');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE dd_guest_links ENABLE ROW LEVEL SECURITY;
            ALTER TABLE dd_guest_links FORCE ROW LEVEL SECURITY;

            CREATE POLICY dd_guest_links_scope ON dd_guest_links
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE dd_data_room_items ENABLE ROW LEVEL SECURITY;
            ALTER TABLE dd_data_room_items FORCE ROW LEVEL SECURITY;

            CREATE POLICY dd_data_room_items_scope ON dd_data_room_items
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
