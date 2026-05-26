<?php

declare(strict_types=1);

use App\Models\KnowledgeEntryDraft;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_entry_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('source_type', 80);
            $table->uuid('source_id')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('category', 80);
            $table->string('title', 180);
            $table->text('body');
            $table->jsonb('tags')->nullable();
            $table->jsonb('source_attribution')->nullable();
            $table->string('state', 32)->default(KnowledgeEntryDraft::STATE_PENDING);
            $table->foreignUuid('accepted_entry_id')->nullable()->constrained('knowledge_entries')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['author_user_id', 'source_type', 'source_id'], 'knowledge_entry_drafts_source_unique');
            $table->index(['author_user_id', 'state', 'updated_at']);
            $table->index(['source_type', 'source_id']);
            $table->index('accepted_entry_id');
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_entry_drafts');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(sprintf(
            <<<'SQL'
                ALTER TABLE knowledge_entry_drafts
                    ADD CONSTRAINT knowledge_entry_drafts_state_check CHECK (state IN ('%s'));

                ALTER TABLE knowledge_entry_drafts ENABLE ROW LEVEL SECURITY;
                ALTER TABLE knowledge_entry_drafts FORCE ROW LEVEL SECURITY;

                CREATE POLICY knowledge_entry_drafts_author_scope ON knowledge_entry_drafts
                    USING (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR author_user_id::text = fsa_current_user_id()
                    )
                    WITH CHECK (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR author_user_id::text = fsa_current_user_id()
                    );
            SQL,
            implode("','", KnowledgeEntryDraft::states()),
        ));
    }
};
