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
        Schema::create('dd_workstreams', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('dd_engagement_id')->constrained('dd_engagements')->cascadeOnDelete();
            $table->string('workstream', 80);
            $table->string('status', 40)->default('pending');
            $table->foreignUuid('analysis_run_id')->nullable()->constrained('analysis_runs')->nullOnDelete();
            $table->jsonb('data_room_item_ids')->nullable();
            $table->unsignedInteger('verification_weight')->default(0);
            $table->jsonb('nz_checks')->nullable();
            $table->string('paused_reason')->nullable();
            $table->foreignId('ran_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('ran_at')->nullable();
            $table->timestampsTz();

            $table->unique(['dd_engagement_id', 'workstream']);
            $table->index(['client_id', 'status']);
            $table->index('analysis_run_id');
            $table->index('ran_by_user_id');
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('dd_workstreams');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE dd_workstreams ENABLE ROW LEVEL SECURITY;
            ALTER TABLE dd_workstreams FORCE ROW LEVEL SECURITY;

            CREATE POLICY dd_workstreams_scope ON dd_workstreams
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
