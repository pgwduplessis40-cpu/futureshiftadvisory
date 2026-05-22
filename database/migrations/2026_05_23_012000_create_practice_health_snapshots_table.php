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
        Schema::create('practice_health_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('scope', 32);
            $table->foreignId('advisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('client_ids');
            $table->jsonb('metrics');
            $table->timestampTz('generated_at');
            $table->timestampsTz();

            $table->index(['scope', 'generated_at']);
            $table->index(['advisor_user_id', 'generated_at']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_health_snapshots');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE practice_health_snapshots ENABLE ROW LEVEL SECURITY;
            ALTER TABLE practice_health_snapshots FORCE ROW LEVEL SECURITY;

            CREATE POLICY practice_health_snapshots_scope ON practice_health_snapshots
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR advisor_user_id::text = fsa_current_user_id()
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR advisor_user_id::text = fsa_current_user_id()
                );
        SQL);
    }
};
