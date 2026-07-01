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
        Schema::create('accounting_invoice_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->foreignUuid('payment_schedule_id')->nullable()->constrained('payment_schedules')->nullOnDelete();
            $table->foreignUuid('practice_accounting_connection_id')->nullable()->constrained('practice_accounting_connections')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('external_tenant_id')->nullable();
            $table->string('status', 24);
            $table->unsignedSmallInteger('term_months');
            $table->decimal('monthly_amount', 12, 2);
            $table->decimal('gst_rate_percent', 5, 2);
            $table->string('currency', 3)->default('NZD');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['proposal_id', 'provider']);
            $table->index(['client_id', 'status']);
            $table->index(['provider', 'status']);
            $table->index('practice_accounting_connection_id');
        });

        Schema::create('accounting_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('accounting_invoice_batch_id')->constrained('accounting_invoice_batches')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->unsignedSmallInteger('sequence');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('amount_ex_gst', 12, 2);
            $table->decimal('gst_amount', 12, 2);
            $table->decimal('amount_inc_gst', 12, 2);
            $table->string('external_contact_id')->nullable();
            $table->string('external_invoice_id')->nullable();
            $table->string('external_invoice_number')->nullable();
            $table->string('status', 24);
            $table->jsonb('payload')->nullable();
            $table->jsonb('response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->timestampsTz();

            $table->unique(['accounting_invoice_batch_id', 'sequence']);
            $table->index(['client_id', 'status']);
            $table->index(['proposal_id', 'status']);
            $table->index(['provider', 'external_invoice_id']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_invoices');
        Schema::dropIfExists('accounting_invoice_batches');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['accounting_invoice_batches', 'accounting_invoices'] as $table) {
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
