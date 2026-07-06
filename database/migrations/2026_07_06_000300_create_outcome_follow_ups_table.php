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
        Schema::create('outcome_follow_ups', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('entrepreneur_profile_id')->nullable()->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->foreignUuid('plan_assessment_id')->nullable()->constrained('plan_assessments')->nullOnDelete();
            $table->foreignUuid('dd_engagement_id')->nullable()->constrained('dd_engagements')->cascadeOnDelete();
            $table->foreignUuid('service_activation_id')->nullable()->constrained('service_activations')->nullOnDelete();
            $table->foreignUuid('conversion_outcome_id')->nullable()->constrained('conversion_outcomes')->nullOnDelete();
            $table->foreignUuid('dd_outcome_record_id')->nullable()->constrained('dd_outcome_records')->nullOnDelete();
            $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedSmallInteger('cadence_month');
            $table->string('status', 40)->default('pending');
            $table->timestampTz('engagement_completed_at');
            $table->timestampTz('due_at');
            $table->timestampTz('completed_at')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->jsonb('outcome_signal')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status', 'due_at']);
            $table->index(['entrepreneur_profile_id', 'status', 'due_at']);
            $table->index(['subject_type', 'cadence_month']);
            $table->unique(['plan_assessment_id', 'cadence_month'], 'outcome_follow_ups_plan_cadence_unique');
            $table->unique(['dd_engagement_id', 'cadence_month'], 'outcome_follow_ups_dd_cadence_unique');
        });

        $this->installPrivacyPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('outcome_follow_ups');
    }

    private function installPrivacyPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE outcome_follow_ups
            ADD CONSTRAINT outcome_follow_ups_subject_check
            CHECK (client_id IS NOT NULL OR entrepreneur_profile_id IS NOT NULL)
        SQL);

        DB::unprepared(<<<'SQL'
            ALTER TABLE outcome_follow_ups ENABLE ROW LEVEL SECURITY;
            ALTER TABLE outcome_follow_ups FORCE ROW LEVEL SECURITY;

            CREATE POLICY outcome_follow_ups_scope ON outcome_follow_ups
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1 FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = outcome_follow_ups.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1 FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = outcome_follow_ups.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );
        SQL);
    }
};
