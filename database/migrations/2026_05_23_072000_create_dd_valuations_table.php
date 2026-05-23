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
        Schema::create('dd_valuations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('dd_engagement_id')->constrained('dd_engagements')->cascadeOnDelete();
            $table->foreignUuid('business_valuation_id')->constrained('business_valuations')->cascadeOnDelete();
            $table->foreignUuid('pv_calculation_id')->constrained('pv_calculations')->cascadeOnDelete();
            $table->char('source_currency', 3)->default('NZD');
            $table->char('normalised_currency', 3)->default('NZD');
            $table->foreignUuid('exchange_rate_id')->nullable()->constrained('exchange_rates')->nullOnDelete();
            $table->decimal('source_to_nzd_rate', 18, 8)->default(1);
            $table->timestampTz('rate_timestamp')->nullable();
            $table->jsonb('normalised_values');
            $table->jsonb('sensitivity');
            $table->jsonb('buyer_position');
            $table->jsonb('source_attributions');
            $table->timestampTz('as_at');
            $table->timestampsTz();

            $table->index(['client_id', 'as_at']);
            $table->index(['dd_engagement_id', 'as_at']);
            $table->index('business_valuation_id');
            $table->index('pv_calculation_id');
            $table->index('exchange_rate_id');
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('dd_valuations');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE dd_valuations ENABLE ROW LEVEL SECURITY;
            ALTER TABLE dd_valuations FORCE ROW LEVEL SECURITY;

            CREATE POLICY dd_valuations_scope ON dd_valuations
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
