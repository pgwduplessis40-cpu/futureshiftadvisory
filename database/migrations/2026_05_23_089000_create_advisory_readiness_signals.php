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
        Schema::create('advisory_readiness_signals', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('entrepreneur_profile_id')->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->foreignUuid('business_plan_id')->nullable()->constrained('business_plans')->cascadeOnDelete();
            $table->foreignUuid('plan_assessment_id')->nullable()->constrained('plan_assessments')->nullOnDelete();
            $table->decimal('score', 5, 2);
            $table->timestampTz('surfaced_at');
            $table->timestampTz('advisor_notified_at')->nullable();
            $table->timestampsTz();

            $table->unique('entrepreneur_profile_id');
            $table->index(['score', 'surfaced_at']);
            $table->index('advisor_notified_at');
        });

        Schema::table('business_plans', function (Blueprint $table): void {
            $table->timestampTz('living_plan_next_update_at')->nullable()->after('completed_at');
            $table->timestampTz('living_plan_last_prompted_at')->nullable()->after('living_plan_next_update_at');
            $table->timestampTz('living_plan_last_assessed_at')->nullable()->after('living_plan_last_prompted_at');
            $table->jsonb('living_plan_divergence_flags')->nullable()->after('living_plan_last_assessed_at');

            $table->index('living_plan_next_update_at');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::table('business_plans', function (Blueprint $table): void {
            $table->dropIndex(['living_plan_next_update_at']);
            $table->dropColumn([
                'living_plan_next_update_at',
                'living_plan_last_prompted_at',
                'living_plan_last_assessed_at',
                'living_plan_divergence_flags',
            ]);
        });

        Schema::dropIfExists('advisory_readiness_signals');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE advisory_readiness_signals ENABLE ROW LEVEL SECURITY;
            ALTER TABLE advisory_readiness_signals FORCE ROW LEVEL SECURITY;

            CREATE POLICY advisory_readiness_signals_scope ON advisory_readiness_signals
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = advisory_readiness_signals.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = advisory_readiness_signals.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );
        SQL);
    }
};
