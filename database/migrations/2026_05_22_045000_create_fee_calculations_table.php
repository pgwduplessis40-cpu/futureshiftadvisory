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
        Schema::create('fee_calculations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('method', 48);
            $table->jsonb('inputs');
            $table->decimal('suggested_low', 16, 2);
            $table->decimal('suggested_mid', 16, 2);
            $table->decimal('suggested_high', 16, 2);
            $table->decimal('improvement_pv_total', 16, 2)->default(0);
            $table->decimal('risk_cost_pv_total', 16, 2)->default(0);
            $table->decimal('roi_ratio', 12, 4)->default(0);
            $table->jsonb('justification');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'method', 'created_at']);
            $table->index('created_by_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_calculations');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE fee_calculations ENABLE ROW LEVEL SECURITY;
            ALTER TABLE fee_calculations FORCE ROW LEVEL SECURITY;

            CREATE POLICY fee_calculations_client_scope ON fee_calculations
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
