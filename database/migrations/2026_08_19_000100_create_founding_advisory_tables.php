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
        Schema::create('founding_advisory_engagements', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('entrepreneur_profile_id')->unique()->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->foreignUuid('business_plan_id')->constrained('business_plans')->restrictOnDelete();
            $table->foreignUuid('plan_assessment_id')->nullable()->constrained('plan_assessments')->nullOnDelete();
            $table->foreignUuid('advisory_readiness_signal_id')->nullable()->constrained('advisory_readiness_signals')->nullOnDelete();
            $table->foreignUuid('proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
            $table->jsonb('baseline');
            $table->string('status', 48)->default('advisory_ready');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('replan_due_at')->nullable();
            $table->timestampTz('transition_review_at')->nullable();
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index(['status', 'replan_due_at']);
            $table->index('proposal_id');
        });

        Schema::create('founding_roadmap_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('founding_advisory_engagement_id')->constrained('founding_advisory_engagements')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('strategic_plan_id')->nullable()->constrained('strategic_plans')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 40)->default('draft');
            $table->date('planning_start_date');
            $table->date('planning_through_date');
            $table->jsonb('agenda');
            $table->jsonb('replan_input')->nullable();
            $table->jsonb('change_summary')->nullable();
            $table->timestampTz('generated_at')->nullable();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['founding_advisory_engagement_id', 'version']);
            $table->index(['client_id', 'status']);
            $table->index(['founding_advisory_engagement_id', 'status']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('founding_roadmap_versions');
        Schema::dropIfExists('founding_advisory_engagements');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['founding_advisory_engagements', 'founding_roadmap_versions'] as $table) {
            DB::unprepared(<<<SQL
                ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;
                ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;

                CREATE POLICY {$table}_scope ON {$table}
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
};
