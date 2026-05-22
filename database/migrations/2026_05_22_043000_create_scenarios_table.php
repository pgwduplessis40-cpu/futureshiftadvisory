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
        Schema::create('scenarios', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('analysis_run_id')->nullable()->constrained('analysis_runs')->nullOnDelete();
            $table->string('name');
            $table->string('kind', 32);
            $table->jsonb('assumptions');
            $table->jsonb('economic_overlay');
            $table->foreignUuid('pv_calculation_id')->nullable()->constrained('pv_calculations')->nullOnDelete();
            $table->decimal('pv_impact', 16, 2)->default(0);
            $table->unsignedTinyInteger('position');
            $table->boolean('is_client_visible')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'position']);
            $table->index(['client_id', 'is_client_visible']);
            $table->index('analysis_run_id');
            $table->index('pv_calculation_id');
            $table->index('created_by_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('scenarios');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE scenarios ENABLE ROW LEVEL SECURITY;
            ALTER TABLE scenarios FORCE ROW LEVEL SECURITY;

            CREATE POLICY scenarios_client_scope ON scenarios
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
