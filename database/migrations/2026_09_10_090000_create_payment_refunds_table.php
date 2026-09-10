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
        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('service_activation_id')->unique()->constrained('service_activations')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('gateway', 40);
            $table->string('payment_reference', 191);
            $table->string('gateway_ref', 191)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NZD');
            $table->string('status', 40)->default('processing');
            $table->string('idempotency_key', 191)->unique();
            $table->text('failure_reason')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status', 'processed_at']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE payment_refunds ENABLE ROW LEVEL SECURITY;
            ALTER TABLE payment_refunds FORCE ROW LEVEL SECURITY;

            CREATE POLICY payment_refunds_client_scope ON payment_refunds
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
        Schema::dropIfExists('payment_refunds');
    }
};
