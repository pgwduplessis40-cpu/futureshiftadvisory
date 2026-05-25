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
        Schema::create('nz_tool_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('external_tenant_id')->nullable();
            $table->string('status', 24)->default('connected');
            $table->text('token_envelope');
            $table->jsonb('token_envelope_meta');
            $table->jsonb('scopes');
            $table->jsonb('last_sync_payload')->nullable();
            $table->foreignId('connected_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('connected_at');
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'provider', 'status']);
            $table->index(['provider', 'last_synced_at']);
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX nz_tool_connections_unique_connected
                ON nz_tool_connections (client_id, provider)
                WHERE status = 'connected'
            SQL);

        DB::unprepared(<<<'SQL'
            ALTER TABLE nz_tool_connections ENABLE ROW LEVEL SECURITY;
            ALTER TABLE nz_tool_connections FORCE ROW LEVEL SECURITY;
            CREATE POLICY nz_tool_connections_client_scope ON nz_tool_connections
                USING (
                    fsa_current_role() = 'super_admin'
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() = 'super_admin'
                    OR client_id::text = ANY (fsa_current_client_ids())
                );
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('nz_tool_connections');
    }
};
