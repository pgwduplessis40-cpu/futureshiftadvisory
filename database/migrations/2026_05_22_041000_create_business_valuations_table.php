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
        Schema::create('business_valuations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('pv_calculation_id')->constrained('pv_calculations')->cascadeOnDelete();
            $table->jsonb('sde_value');
            $table->jsonb('ebitda_value');
            $table->jsonb('dcf_value');
            $table->decimal('reconciled_low', 16, 2);
            $table->decimal('reconciled_mid', 16, 2);
            $table->decimal('reconciled_high', 16, 2);
            $table->jsonb('adjustments')->nullable();
            $table->text('data_quality_disclaimer')->nullable();
            $table->jsonb('source_attributions');
            $table->timestampTz('as_at');
            $table->timestampsTz();

            $table->index(['client_id', 'as_at']);
            $table->index('pv_calculation_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('business_valuations');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE business_valuations ENABLE ROW LEVEL SECURITY;
            ALTER TABLE business_valuations FORCE ROW LEVEL SECURITY;

            CREATE POLICY business_valuations_client_scope ON business_valuations
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
