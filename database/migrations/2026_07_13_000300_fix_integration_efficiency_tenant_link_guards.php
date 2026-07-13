<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

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
        SQL);
    }

    public function down(): void
    {
        // The preceding migration owns the trigger function lifecycle.
    }
};
