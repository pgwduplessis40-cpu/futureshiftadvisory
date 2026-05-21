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
        Schema::create('knowledge_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('category', 80);
            $table->string('title', 180);
            $table->text('body');
            $table->jsonb('tags')->nullable();
            $table->timestampsTz();

            $table->index(['author_user_id', 'updated_at']);
            $table->index(['author_user_id', 'category']);
            $table->index('client_id');
        });

        $this->installSearchAndRls();
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_entries');
    }

    private function installSearchAndRls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE knowledge_entries
                ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                    setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
                    setweight(to_tsvector('english', coalesce(category, '')), 'B') ||
                    setweight(to_tsvector('english', coalesce(tags::text, '')), 'B') ||
                    setweight(to_tsvector('english', coalesce(body, '')), 'C')
                ) STORED;

            CREATE INDEX knowledge_entries_search_vector_gin
                ON knowledge_entries USING GIN (search_vector);

            ALTER TABLE knowledge_entries ENABLE ROW LEVEL SECURITY;
            ALTER TABLE knowledge_entries FORCE ROW LEVEL SECURITY;

            CREATE POLICY knowledge_entries_author_scope ON knowledge_entries
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR author_user_id::text = fsa_current_user_id()
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR author_user_id::text = fsa_current_user_id()
                );
        SQL);
    }
};
