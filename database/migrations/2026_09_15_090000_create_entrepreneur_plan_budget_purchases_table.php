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
        Schema::create('entrepreneur_plan_budget_purchases', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('entrepreneur_profile_id')->unique()->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('service_rate_package_id')->nullable()->constrained('service_rate_packages')->nullOnDelete();
            $table->foreignUuid('payment_id')->nullable()->unique()->constrained('payments')->nullOnDelete();
            $table->foreignUuid('approved_idea_validation_id')->nullable()->constrained('idea_validations')->nullOnDelete();
            $table->string('status', 40)->default('payment_pending');
            $table->decimal('amount_ex_gst', 12, 2)->nullable();
            $table->decimal('gst_amount', 12, 2)->nullable();
            $table->decimal('amount_including_gst', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->jsonb('package_snapshot')->nullable();
            $table->string('stripe_payment_intent_ref', 191)->nullable()->unique();
            $table->timestampTz('payment_intent_created_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['advisor_id', 'status']);
            $table->index(['approved_idea_validation_id', 'activated_at']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE entrepreneur_plan_budget_purchases ENABLE ROW LEVEL SECURITY;
            ALTER TABLE entrepreneur_plan_budget_purchases FORCE ROW LEVEL SECURITY;

            CREATE POLICY entrepreneur_plan_budget_purchases_scope ON entrepreneur_plan_budget_purchases
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
        Schema::dropIfExists('entrepreneur_plan_budget_purchases');
    }
};
