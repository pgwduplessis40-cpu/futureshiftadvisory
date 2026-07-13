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
        Schema::create('integration_fee_bands', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('complexity_band', 8);
            $table->string('delivery_mode', 24);
            $table->decimal('fee_low', 12, 2);
            $table->decimal('fee_mid', 12, 2);
            $table->decimal('fee_high', 12, 2);
            $table->string('currency', 3)->default('NZD');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['complexity_band', 'delivery_mode'], 'integration_fee_bands_band_mode_unique');
        });

        Schema::create('integration_scopes', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->jsonb('systems')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('tasks')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('connections')->default(DB::raw("'[]'::jsonb"));
            $table->string('delivery_mode', 24)->nullable();
            $table->decimal('partner_cost_estimate', 12, 2)->nullable();
            $table->decimal('partner_margin_percent', 5, 2)->nullable();
            $table->decimal('capture_percent', 5, 2)->default(80);
            $table->unsignedSmallInteger('savings_horizon_years')->default(3);
            $table->decimal('discount_rate_percent', 5, 2)->default(12);
            $table->jsonb('computed')->default(DB::raw("'{}'::jsonb"));
            $table->foreignUuid('pv_calculation_id')->nullable()->constrained('pv_calculations')->nullOnDelete();
            $table->jsonb('source_document_ids')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('extracted_rows')->default(DB::raw("'[]'::jsonb"));
            $table->decimal('quoted_fee', 12, 2)->nullable();
            $table->text('fee_override_reason')->nullable();
            $table->string('status', 24)->default('not_started');
            $table->jsonb('flags')->default(DB::raw("'[]'::jsonb"));
            $table->foreignUuid('proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
            $table->foreignUuid('goal_id')->nullable()->constrained('goals')->nullOnDelete();
            $table->foreignUuid('scoping_credit_adjustment_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index('proposal_id');
            $table->index('goal_id');
        });

        Schema::create('billing_adjustments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('type', 48);
            $table->foreignUuid('source_service_activation_id')->nullable()->constrained('service_activations')->nullOnDelete();
            $table->string('source_payment_reference', 191)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NZD');
            $table->string('status', 24)->default('available');
            $table->foreignUuid('applied_to_proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
            $table->timestampTz('applied_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index('source_service_activation_id');
            $table->unique('source_service_activation_id', 'billing_adjustments_source_activation_unique');
        });

        Schema::table('integration_scopes', function (Blueprint $table): void {
            $table->foreign('scoping_credit_adjustment_id')
                ->references('id')
                ->on('billing_adjustments')
                ->nullOnDelete();
        });

        Schema::create('payment_installments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('payment_schedule_id')->constrained('payment_schedules')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->date('due_date');
            $table->decimal('base_amount', 12, 2);
            $table->decimal('credit_applied', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->string('status', 40)->default('due');
            $table->uuid('active_payment_id')->nullable();
            $table->string('attempted_gateway', 40)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('processing_started_at')->nullable();
            $table->unsignedInteger('confirmation_attempts')->default(0);
            $table->timestampTz('next_confirmation_at')->nullable();
            $table->timestampTz('confirmation_deadline')->nullable();
            $table->timestampsTz();

            $table->unique(['payment_schedule_id', 'sequence'], 'payment_installments_schedule_sequence_unique');
            $table->index(['client_id', 'status', 'due_date']);
            $table->index(['status', 'next_attempt_at']);
            $table->index(['status', 'next_confirmation_at']);
        });

        Schema::create('billing_adjustment_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('adjustment_id')->constrained('billing_adjustments')->cascadeOnDelete();
            $table->foreignUuid('payment_installment_id')->constrained('payment_installments')->cascadeOnDelete();
            $table->decimal('amount_applied', 12, 2);
            $table->timestampsTz();

            $table->unique(['adjustment_id', 'payment_installment_id'], 'billing_adjustment_application_unique');
            $table->index(['client_id', 'payment_installment_id']);
        });

        Schema::table('fee_calculations', function (Blueprint $table): void {
            $table->foreignUuid('integration_scope_id')->nullable()->after('client_id')->constrained('integration_scopes')->nullOnDelete();
            $table->index('integration_scope_id');
        });

        Schema::table('service_activations', function (Blueprint $table): void {
            $table->foreignUuid('proposal_id')->nullable()->after('client_id')->constrained('proposals')->nullOnDelete();
            $table->unique('proposal_id', 'service_activations_proposal_unique');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignUuid('payment_installment_id')->nullable()->after('payment_schedule_id')->constrained('payment_installments')->nullOnDelete();
            $table->string('idempotency_key', 191)->nullable()->after('gateway_ref');
            $table->dropUnique(['payment_schedule_id', 'attempt']);
            $table->unique(['payment_installment_id', 'attempt'], 'payments_installment_attempt_unique');
            $table->index('idempotency_key');
        });

        Schema::table('payment_installments', function (Blueprint $table): void {
            $table->foreign('active_payment_id')->references('id')->on('payments')->nullOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX payments_legacy_schedule_attempt_unique ON payments (payment_schedule_id, attempt) WHERE payment_installment_id IS NULL");
            DB::statement("CREATE UNIQUE INDEX payments_installment_active_or_succeeded_unique ON payments (payment_installment_id) WHERE payment_installment_id IS NOT NULL AND status IN ('pending', 'retrying', 'succeeded')");
            DB::statement("CREATE UNIQUE INDEX service_activations_open_type_unique ON service_activations (client_id, service_type) WHERE status NOT IN ('cancelled', 'closed', 'rejected')");
            $this->installTenantLinkGuards();
        }

        $this->installClientRls();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS payments_legacy_schedule_attempt_unique');
            DB::statement('DROP INDEX IF EXISTS payments_installment_active_or_succeeded_unique');
            DB::statement('DROP INDEX IF EXISTS service_activations_open_type_unique');
            DB::statement('DROP FUNCTION IF EXISTS fsa_assert_integration_efficiency_tenant_links() CASCADE');
        }

        Schema::table('payment_installments', function (Blueprint $table): void {
            $table->dropForeign(['active_payment_id']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['idempotency_key']);
            $table->dropUnique('payments_installment_attempt_unique');
            $table->dropForeign(['payment_installment_id']);
            $table->dropColumn(['payment_installment_id', 'idempotency_key']);
            $table->unique(['payment_schedule_id', 'attempt']);
        });

        Schema::table('service_activations', function (Blueprint $table): void {
            $table->dropUnique('service_activations_proposal_unique');
            $table->dropForeign(['proposal_id']);
            $table->dropColumn('proposal_id');
        });

        Schema::table('fee_calculations', function (Blueprint $table): void {
            $table->dropIndex(['integration_scope_id']);
            $table->dropForeign(['integration_scope_id']);
            $table->dropColumn('integration_scope_id');
        });

        Schema::table('integration_scopes', function (Blueprint $table): void {
            $table->dropForeign(['scoping_credit_adjustment_id']);
        });

        Schema::dropIfExists('billing_adjustment_applications');
        Schema::dropIfExists('payment_installments');
        Schema::dropIfExists('billing_adjustments');
        Schema::dropIfExists('integration_scopes');
        Schema::dropIfExists('integration_fee_bands');
    }

    private function installClientRls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['integration_scopes', 'billing_adjustments', 'payment_installments', 'billing_adjustment_applications'] as $table) {
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

        DB::unprepared(<<<'SQL'
            ALTER TABLE integration_fee_bands ENABLE ROW LEVEL SECURITY;
            ALTER TABLE integration_fee_bands FORCE ROW LEVEL SECURITY;
            CREATE POLICY integration_fee_bands_read_scope ON integration_fee_bands
                FOR SELECT
                USING (fsa_current_role() IN ('super_admin', 'system', 'advisor', 'junior_advisor'));
            CREATE POLICY integration_fee_bands_write_scope ON integration_fee_bands
                USING (fsa_current_role() IN ('super_admin', 'system'))
                WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));
        SQL);
    }

    private function installTenantLinkGuards(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fsa_assert_integration_efficiency_tenant_links()
            RETURNS trigger AS $$
            DECLARE
                linked_client uuid;
            BEGIN
                IF TG_TABLE_NAME = 'payment_installments' THEN
                    SELECT client_id INTO linked_client FROM payment_schedules WHERE id = NEW.payment_schedule_id;
                    IF linked_client IS NULL OR linked_client <> NEW.client_id THEN
                        RAISE EXCEPTION 'payment installment client must match its payment schedule';
                    END IF;
                ELSIF TG_TABLE_NAME = 'payments' THEN
                    IF NEW.payment_installment_id IS NOT NULL THEN
                        SELECT client_id INTO linked_client FROM payment_installments WHERE id = NEW.payment_installment_id;
                        IF linked_client IS NULL OR linked_client <> NEW.client_id THEN
                            RAISE EXCEPTION 'payment client must match its payment installment';
                        END IF;
                    END IF;
                ELSIF TG_TABLE_NAME = 'billing_adjustments' THEN
                    IF NEW.source_service_activation_id IS NOT NULL THEN
                        SELECT client_id INTO linked_client FROM service_activations WHERE id = NEW.source_service_activation_id;
                        IF linked_client IS NULL OR linked_client <> NEW.client_id THEN
                            RAISE EXCEPTION 'billing adjustment client must match its source activation';
                        END IF;
                    END IF;
                ELSIF TG_TABLE_NAME = 'billing_adjustment_applications' THEN
                    SELECT client_id INTO linked_client FROM billing_adjustments WHERE id = NEW.adjustment_id;
                    IF linked_client IS NULL OR linked_client <> NEW.client_id THEN
                        RAISE EXCEPTION 'billing adjustment application client must match its adjustment';
                    END IF;
                    SELECT client_id INTO linked_client FROM payment_installments WHERE id = NEW.payment_installment_id;
                    IF linked_client IS NULL OR linked_client <> NEW.client_id THEN
                        RAISE EXCEPTION 'billing adjustment application client must match its installment';
                    END IF;
                ELSIF TG_TABLE_NAME = 'fee_calculations' THEN
                    IF NEW.integration_scope_id IS NOT NULL THEN
                        SELECT client_id INTO linked_client FROM integration_scopes WHERE id = NEW.integration_scope_id;
                        IF linked_client IS NULL OR linked_client <> NEW.client_id THEN
                            RAISE EXCEPTION 'fee calculation client must match its integration scope';
                        END IF;
                    END IF;
                ELSIF TG_TABLE_NAME = 'service_activations' THEN
                    IF NEW.proposal_id IS NOT NULL THEN
                        SELECT client_id INTO linked_client FROM proposals WHERE id = NEW.proposal_id;
                        IF linked_client IS NULL OR linked_client <> NEW.client_id THEN
                            RAISE EXCEPTION 'service activation client must match its proposal';
                        END IF;
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER payment_installments_tenant_link_guard BEFORE INSERT OR UPDATE ON payment_installments
                FOR EACH ROW EXECUTE FUNCTION fsa_assert_integration_efficiency_tenant_links();
            CREATE TRIGGER payments_installment_tenant_link_guard BEFORE INSERT OR UPDATE ON payments
                FOR EACH ROW EXECUTE FUNCTION fsa_assert_integration_efficiency_tenant_links();
            CREATE TRIGGER billing_adjustments_tenant_link_guard BEFORE INSERT OR UPDATE ON billing_adjustments
                FOR EACH ROW EXECUTE FUNCTION fsa_assert_integration_efficiency_tenant_links();
            CREATE TRIGGER billing_adjustment_applications_tenant_link_guard BEFORE INSERT OR UPDATE ON billing_adjustment_applications
                FOR EACH ROW EXECUTE FUNCTION fsa_assert_integration_efficiency_tenant_links();
            CREATE TRIGGER fee_calculations_integration_tenant_link_guard BEFORE INSERT OR UPDATE ON fee_calculations
                FOR EACH ROW EXECUTE FUNCTION fsa_assert_integration_efficiency_tenant_links();
            CREATE TRIGGER service_activations_proposal_tenant_link_guard BEFORE INSERT OR UPDATE ON service_activations
                FOR EACH ROW EXECUTE FUNCTION fsa_assert_integration_efficiency_tenant_links();
        SQL);
    }
};
