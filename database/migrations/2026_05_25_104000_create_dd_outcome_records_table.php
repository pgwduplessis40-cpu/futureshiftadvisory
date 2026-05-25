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
        Schema::create('dd_outcome_records', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('dd_engagement_id')->constrained('dd_engagements')->cascadeOnDelete();
            $table->decimal('recorded_price', 16, 2)->nullable();
            $table->jsonb('actual_outcome');
            $table->timestampTz('recorded_at');
            $table->timestampsTz();

            $table->index(['client_id', 'recorded_at']);
            $table->index(['dd_engagement_id', 'recorded_at']);
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('dd_outcome_records');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE dd_outcome_records ENABLE ROW LEVEL SECURITY;
            ALTER TABLE dd_outcome_records FORCE ROW LEVEL SECURITY;

            CREATE POLICY dd_outcome_records_scope ON dd_outcome_records
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
