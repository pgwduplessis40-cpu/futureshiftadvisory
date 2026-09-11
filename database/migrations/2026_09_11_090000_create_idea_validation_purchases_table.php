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
        Schema::table('payments', function (Blueprint $table): void {
            // Self-service purchases are one-off payments, rather than a
            // proposal payment-schedule attempt. Keeping this nullable lets
            // them use the established Payment and Receipt records without a
            // fake proposal or schedule.
            $table->foreignUuid('payment_schedule_id')->nullable()->change();
        });

        Schema::create('idea_validation_purchases', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('terms_version_id')->constrained('terms_versions')->restrictOnDelete();
            $table->foreignUuid('service_rate_package_id')->nullable()->constrained('service_rate_packages')->nullOnDelete();
            $table->foreignUuid('payment_id')->nullable()->unique()->constrained('payments')->nullOnDelete();
            $table->foreignUuid('service_activation_id')->nullable()->unique()->constrained('service_activations')->nullOnDelete();
            $table->string('status', 40)->default('email_verification_pending');
            $table->timestampTz('email_verified_at')->nullable();
            $table->decimal('amount_ex_gst', 12, 2)->nullable();
            $table->decimal('gst_amount', 12, 2)->nullable();
            $table->decimal('amount_including_gst', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->jsonb('package_snapshot')->nullable();
            $table->string('stripe_payment_intent_ref', 191)->nullable()->unique();
            $table->timestampTz('payment_intent_created_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'status']);
            $table->index(['advisor_id', 'status']);
            $table->index(['client_id', 'status']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE idea_validation_purchases ENABLE ROW LEVEL SECURITY;
            ALTER TABLE idea_validation_purchases FORCE ROW LEVEL SECURITY;

            CREATE POLICY idea_validation_purchases_scope ON idea_validation_purchases
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR user_id::text = fsa_current_user_id()
                    OR advisor_id::text = fsa_current_user_id()
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR user_id::text = fsa_current_user_id()
                    OR advisor_id::text = fsa_current_user_id()
                );
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_validation_purchases');

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignUuid('payment_schedule_id')->nullable(false)->change();
        });
    }
};
