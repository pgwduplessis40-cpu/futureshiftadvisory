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
        Schema::create('documents', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('client_id')->nullable();
            $table->uuid('entrepreneur_profile_id')->nullable();
            $table->string('category', 80);
            $table->string('original_filename');
            $table->string('stored_path', 600)->unique();
            $table->unsignedBigInteger('byte_size');
            $table->string('mime_type', 120)->nullable();
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scanner_result', 16)->default('pending');
            $table->jsonb('scanner_payload')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'category']);
            $table->index(['client_id', 'scanner_result']);
            $table->index('entrepreneur_profile_id');
            $table->index('uploaded_by_user_id');
            $table->index('sha256');
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE documents ENABLE ROW LEVEL SECURITY;
            ALTER TABLE documents FORCE ROW LEVEL SECURITY;

            CREATE POLICY documents_client_scope ON documents
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
        SQL);
    }
};
