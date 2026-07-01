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
        Schema::create('practice_accounting_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('provider', 32);
            $table->string('external_tenant_id')->nullable();
            $table->string('external_tenant_name')->nullable();
            $table->string('external_tenant_type')->nullable();
            $table->string('status', 24);
            $table->text('token_envelope');
            $table->jsonb('token_envelope_meta')->nullable();
            $table->jsonb('scopes')->nullable();
            $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('connected_at');
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_invoice_sync_at')->nullable();
            $table->timestampsTz();

            $table->index(['provider', 'status']);
            $table->index(['provider', 'external_tenant_id']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_accounting_connections');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE practice_accounting_connections ENABLE ROW LEVEL SECURITY;
            ALTER TABLE practice_accounting_connections FORCE ROW LEVEL SECURITY;

            CREATE POLICY practice_accounting_connections_admin_scope ON practice_accounting_connections
                USING (fsa_current_role() IN ('super_admin', 'system'))
                WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));
        SQL);
    }
};
