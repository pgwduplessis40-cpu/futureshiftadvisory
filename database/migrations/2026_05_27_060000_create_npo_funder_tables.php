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
        Schema::create('funders', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('type', 80);
            $table->jsonb('funding_windows');
            $table->jsonb('criteria');
            $table->jsonb('reporting_requirements');
            $table->jsonb('renewal_intelligence');
            $table->timestampTz('last_verified_at')->nullable();
            $table->foreignUuid('source_learning_update_id')->nullable()->constrained('learning_updates')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique('name');
            $table->index(['type', 'last_verified_at']);
            $table->index('source_learning_update_id');
        });

        Schema::create('client_funder_records', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->uuid('npo_engagement_id')->nullable();
            $table->foreignUuid('funder_id')->constrained('funders')->restrictOnDelete();
            $table->string('grant_name')->nullable();
            $table->decimal('grant_amount', 14, 2);
            $table->string('currency', 3)->default('NZD');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->jsonb('conditions')->nullable();
            $table->date('reporting_deadline')->nullable();
            $table->date('next_application_window_opens_at')->nullable();
            $table->date('next_application_window_closes_at')->nullable();
            $table->date('grant_expiry_at')->nullable();
            $table->unsignedTinyInteger('renewal_probability')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('history')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'period_end']);
            $table->index(['client_id', 'reporting_deadline']);
            $table->index(['client_id', 'next_application_window_opens_at']);
            $table->index(['client_id', 'grant_expiry_at']);
            $table->index('npo_engagement_id');
        });

        Schema::create('client_funder_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('client_funder_record_id')->constrained('client_funder_records')->cascadeOnDelete();
            $table->string('alert_key');
            $table->string('type', 80);
            $table->string('severity', 40);
            $table->text('message');
            $table->date('due_on')->nullable();
            $table->timestampTz('triggered_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->unique('alert_key');
            $table->index(['client_id', 'severity']);
            $table->index(['client_funder_record_id', 'type']);
            $table->index('resolved_at');
        });

        if ($this->onPostgres()) {
            DB::statement(<<<'SQL'
                ALTER TABLE client_funder_records
                ADD CONSTRAINT client_funder_records_engagement_client_fk
                FOREIGN KEY (npo_engagement_id, client_id)
                REFERENCES npo_engagements (id, client_id)
                ON DELETE CASCADE
            SQL);

            DB::statement('ALTER TABLE client_funder_records ADD CONSTRAINT client_funder_records_renewal_probability_check CHECK (renewal_probability IS NULL OR renewal_probability BETWEEN 0 AND 100)');
        }

        $this->installRegistryGovernanceTrigger();
        $this->installRlsPolicies();
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::unprepared('DROP TRIGGER IF EXISTS funders_layer34_governance ON funders;');
            DB::unprepared('DROP FUNCTION IF EXISTS fsa_assert_funder_layer34_governance();');
        }

        Schema::dropIfExists('client_funder_alerts');
        Schema::dropIfExists('client_funder_records');
        Schema::dropIfExists('funders');
    }

    private function installRegistryGovernanceTrigger(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fsa_assert_funder_layer34_governance()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.source_learning_update_id IS NULL OR NOT EXISTS (
                    SELECT 1
                    FROM learning_updates
                    WHERE id = NEW.source_learning_update_id
                        AND layer_id = 34
                        AND status IN ('approved', 'implemented')
                ) THEN
                    RAISE EXCEPTION 'Funder registry changes require an approved Layer 34 learning update.';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER funders_layer34_governance
                BEFORE INSERT OR UPDATE ON funders
                FOR EACH ROW EXECUTE FUNCTION fsa_assert_funder_layer34_governance();
        SQL);
    }

    private function installRlsPolicies(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE client_funder_records ENABLE ROW LEVEL SECURITY;
            ALTER TABLE client_funder_records FORCE ROW LEVEL SECURITY;

            CREATE POLICY client_funder_records_client_scope ON client_funder_records
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE client_funder_alerts ENABLE ROW LEVEL SECURITY;
            ALTER TABLE client_funder_alerts FORCE ROW LEVEL SECURITY;

            CREATE POLICY client_funder_alerts_client_scope ON client_funder_alerts
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

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
