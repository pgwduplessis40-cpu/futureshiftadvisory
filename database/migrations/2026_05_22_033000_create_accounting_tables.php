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
        Schema::create('accounting_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('external_tenant_id')->nullable();
            $table->string('status', 24);
            $table->text('token_envelope');
            $table->jsonb('token_envelope_meta')->nullable();
            $table->jsonb('scopes')->nullable();
            $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('connected_at');
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_snapshot_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'provider', 'status']);
            $table->index(['provider', 'external_tenant_id']);
        });

        Schema::create('financial_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('accounting_connection_id')->constrained('accounting_connections')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('source', 80);
            $table->string('source_badge', 32);
            $table->boolean('degraded')->default(false);
            $table->uuid('correlation_id')->nullable();
            $table->jsonb('profit_and_loss');
            $table->jsonb('balance_sheet');
            $table->jsonb('cash_flow');
            $table->jsonb('metrics');
            $table->timestampTz('pulled_at');
            $table->timestampsTz();

            $table->index(['client_id', 'period_end']);
            $table->index(['accounting_connection_id', 'pulled_at']);
            $table->index(['provider', 'pulled_at']);
        });

        $this->installRlsPolicies();
        $this->installSnapshotImmutability();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS financial_snapshots_append_only ON financial_snapshots;');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_financial_snapshot_mutation();');
        }

        Schema::dropIfExists('financial_snapshots');
        Schema::dropIfExists('accounting_connections');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE accounting_connections ENABLE ROW LEVEL SECURITY;
            ALTER TABLE accounting_connections FORCE ROW LEVEL SECURITY;

            CREATE POLICY accounting_connections_client_scope ON accounting_connections
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE financial_snapshots ENABLE ROW LEVEL SECURITY;
            ALTER TABLE financial_snapshots FORCE ROW LEVEL SECURITY;

            CREATE POLICY financial_snapshots_client_scope ON financial_snapshots
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

    private function installSnapshotImmutability(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_financial_snapshot_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'financial_snapshots are append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER financial_snapshots_append_only
                BEFORE UPDATE OR DELETE ON financial_snapshots
                FOR EACH ROW EXECUTE FUNCTION prevent_financial_snapshot_mutation();
        SQL);
    }
};
