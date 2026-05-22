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
        Schema::create('financial_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('accounting_connection_id')->constrained('accounting_connections')->cascadeOnDelete();
            $table->foreignUuid('previous_snapshot_id')->constrained('financial_snapshots')->cascadeOnDelete();
            $table->foreignUuid('current_snapshot_id')->constrained('financial_snapshots')->cascadeOnDelete();
            $table->string('alert_key', 128)->unique();
            $table->string('category', 40);
            $table->string('severity', 24);
            $table->string('metric', 80);
            $table->string('headline');
            $table->text('detail');
            $table->double('previous_value');
            $table->double('current_value');
            $table->double('change_amount');
            $table->double('change_percent')->nullable();
            $table->jsonb('citation');
            $table->timestampTz('surfaced_at');
            $table->timestampTz('notified_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'surfaced_at']);
            $table->index(['client_id', 'category', 'severity']);
            $table->index(['accounting_connection_id', 'metric']);
            $table->index(['previous_snapshot_id', 'current_snapshot_id']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_alerts');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE financial_alerts ENABLE ROW LEVEL SECURITY;
            ALTER TABLE financial_alerts FORCE ROW LEVEL SECURITY;

            CREATE POLICY financial_alerts_client_scope ON financial_alerts
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
