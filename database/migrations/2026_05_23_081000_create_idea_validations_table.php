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
        Schema::create('idea_validations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('entrepreneur_profile_id')->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->text('problem');
            $table->text('target_customer');
            $table->text('solution');
            $table->text('value_proposition');
            $table->text('demand_signal');
            $table->text('revenue_model');
            $table->jsonb('ai_evaluation');
            $table->jsonb('viability_alerts')->nullable();
            $table->timestampTz('evaluated_at');
            $table->foreignId('evaluated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('advisor_gate_passed_at')->nullable();
            $table->foreignId('advisor_gate_passed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('advisor_gate_note')->nullable();
            $table->timestampsTz();

            $table->index(['entrepreneur_profile_id', 'evaluated_at']);
            $table->index('advisor_gate_passed_at');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_validations');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE idea_validations ENABLE ROW LEVEL SECURITY;
            ALTER TABLE idea_validations FORCE ROW LEVEL SECURITY;

            CREATE POLICY idea_validations_scope ON idea_validations
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = idea_validations.entrepreneur_profile_id
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
                        WHERE entrepreneur_profiles.id = idea_validations.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );
        SQL);
    }
};
