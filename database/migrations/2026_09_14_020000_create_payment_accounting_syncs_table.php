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
        Schema::create('payment_accounting_syncs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('payment_id')->unique()->constrained('payments')->cascadeOnDelete();
            $table->foreignUuid('payment_refund_id')->nullable()->unique()->constrained('payment_refunds')->nullOnDelete();
            $table->foreignUuid('practice_accounting_connection_id')->nullable()->constrained('practice_accounting_connections')->nullOnDelete();
            $table->string('provider', 32)->default('xero');
            $table->string('source', 40)->default('idea_validation');
            $table->decimal('amount_ex_gst', 12, 2);
            $table->decimal('gst_amount', 12, 2);
            $table->decimal('amount_including_gst', 12, 2);
            $table->string('currency', 3)->default('NZD');
            $table->string('status', 24)->default('pending');
            $table->string('refund_status', 24)->default('not_requested');
            $table->string('external_contact_id')->nullable();
            $table->string('external_invoice_id')->nullable();
            $table->string('external_invoice_number')->nullable();
            $table->string('external_payment_id')->nullable();
            $table->string('external_credit_note_id')->nullable();
            $table->string('external_credit_note_number')->nullable();
            $table->text('error_message')->nullable();
            $table->text('refund_error_message')->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index(['provider', 'status']);
            $table->index(['refund_status', 'refunded_at']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE payment_accounting_syncs ENABLE ROW LEVEL SECURITY;
            ALTER TABLE payment_accounting_syncs FORCE ROW LEVEL SECURITY;

            CREATE POLICY payment_accounting_syncs_client_scope ON payment_accounting_syncs
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

    public function down(): void
    {
        Schema::dropIfExists('payment_accounting_syncs');
    }
};
