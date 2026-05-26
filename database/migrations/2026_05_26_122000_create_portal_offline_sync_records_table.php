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
        Schema::create('portal_offline_sync_records', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('operation', 180);
            $table->string('idempotency_key', 160);
            $table->char('request_fingerprint', 64);
            $table->jsonb('response_payload');
            $table->unsignedSmallInteger('status_code')->default(200);
            $table->timestampsTz();

            $table->unique(['user_id', 'client_id', 'operation', 'idempotency_key'], 'portal_offline_sync_unique');
            $table->index(['client_id', 'operation']);
            $table->index('request_fingerprint');
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_offline_sync_records');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE portal_offline_sync_records ENABLE ROW LEVEL SECURITY;
            ALTER TABLE portal_offline_sync_records FORCE ROW LEVEL SECURITY;

            CREATE POLICY portal_offline_sync_records_scope ON portal_offline_sync_records
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        user_id::text = fsa_current_user_id()
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        user_id::text = fsa_current_user_id()
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                );
        SQL);
    }
};
