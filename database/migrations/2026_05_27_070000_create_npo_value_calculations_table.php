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
        Schema::create('npo_value_calculations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->uuid('npo_engagement_id');
            $table->string('type', 80);
            $table->unsignedTinyInteger('dimension_number');
            $table->string('programme_type', 120)->nullable();
            $table->string('size_band', 40)->nullable();
            $table->string('rating', 40);
            $table->decimal('projection_mid', 16, 2)->default(0);
            $table->decimal('projection_low', 16, 2)->default(0);
            $table->decimal('projection_high', 16, 2)->default(0);
            $table->jsonb('inputs');
            $table->jsonb('result');
            $table->jsonb('benchmark_config');
            $table->jsonb('source_attributions');
            $table->text('stable_assumption_disclosure');
            $table->timestampTz('calculated_at');
            $table->timestampsTz();

            $table->index(['client_id', 'type', 'calculated_at']);
            $table->index(['npo_engagement_id', 'type', 'calculated_at'], 'npo_value_calcs_engagement_type_at_idx');
            $table->index(['dimension_number', 'rating']);
        });

        if ($this->onPostgres()) {
            DB::statement(<<<'SQL'
                ALTER TABLE npo_value_calculations
                ADD CONSTRAINT npo_value_calculations_engagement_client_fk
                FOREIGN KEY (npo_engagement_id, client_id)
                REFERENCES npo_engagements (id, client_id)
                ON DELETE CASCADE
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE npo_value_calculations
                ADD CONSTRAINT npo_value_calculations_dimension_check
                    CHECK (dimension_number BETWEEN 1 AND 8),
                ADD CONSTRAINT npo_value_calculations_projection_range_check
                    CHECK (projection_low <= projection_mid AND projection_mid <= projection_high),
                ADD CONSTRAINT npo_value_calculations_type_check
                    CHECK (type IN ('cost_per_beneficiary', 'funding_risk'))
            SQL);
        }

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('npo_value_calculations');
    }

    private function installRlsPolicies(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE npo_value_calculations ENABLE ROW LEVEL SECURITY;
            ALTER TABLE npo_value_calculations FORCE ROW LEVEL SECURITY;

            CREATE POLICY npo_value_calculations_client_scope ON npo_value_calculations
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
