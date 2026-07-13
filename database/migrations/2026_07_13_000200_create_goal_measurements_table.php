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
        Schema::create('goal_measurements', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('goal_id')->constrained('goals')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('pv_calculation_id')->nullable()->constrained('pv_calculations')->nullOnDelete();
            $table->decimal('pv_realised', 15, 2)->default(0);
            $table->timestampTz('observed_at');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['goal_id', 'observed_at']);
            $table->index(['client_id', 'observed_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE goal_measurements ENABLE ROW LEVEL SECURITY;
                ALTER TABLE goal_measurements FORCE ROW LEVEL SECURITY;
                CREATE POLICY goal_measurements_client_scope ON goal_measurements
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
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_measurements');
    }
};
