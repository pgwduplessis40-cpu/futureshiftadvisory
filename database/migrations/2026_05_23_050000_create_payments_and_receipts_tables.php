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
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('payment_schedule_id')->constrained('payment_schedules')->cascadeOnDelete();
            $table->foreignUuid('payment_authority_id')->nullable()->constrained('payment_authorities')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NZD');
            $table->string('gateway', 40)->nullable();
            $table->string('gateway_ref')->nullable();
            $table->string('status', 40)->default('pending');
            $table->unsignedInteger('attempt')->default(1);
            $table->string('failover_from', 40)->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['payment_schedule_id', 'attempt']);
            $table->index(['client_id', 'status', 'processed_at']);
            $table->index(['payment_authority_id', 'status']);
            $table->index(['gateway', 'status']);
        });

        Schema::create('receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('payment_id')->unique()->constrained('payments')->cascadeOnDelete();
            $table->string('receipt_path');
            $table->text('receipt_sha256_envelope');
            $table->jsonb('receipt_envelope_meta')->nullable();
            $table->unsignedInteger('receipt_byte_size');
            $table->timestampTz('generated_at');
            $table->timestampsTz();

            $table->index(['client_id', 'generated_at']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payments');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['payments', 'receipts'] as $table) {
            DB::unprepared(<<<SQL
                ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;
                ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;

                CREATE POLICY {$table}_client_scope ON {$table}
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
    }
};
