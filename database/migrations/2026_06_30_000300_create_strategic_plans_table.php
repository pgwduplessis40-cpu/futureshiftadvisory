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
        Schema::create('strategic_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
            $table->foreignUuid('strategic_budget_id')->nullable()->constrained('strategic_budgets')->nullOnDelete();
            $table->string('title');
            $table->string('status', 40)->default('draft');
            $table->text('summary')->nullable();
            $table->jsonb('sections')->nullable();
            $table->timestampTz('generated_at')->nullable();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('deployed_at')->nullable();
            $table->foreignId('deployed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique('proposal_id');
            $table->index(['client_id', 'status']);
            $table->index('strategic_budget_id');
        });

        Schema::create('strategic_plan_milestones', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('strategic_plan_id')->constrained('strategic_plans')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('owner', 40)->default('joint');
            $table->unsignedSmallInteger('due_offset_days')->default(30);
            $table->date('due_date')->nullable();
            $table->string('status', 40)->default('pending');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->text('evidence_notes')->nullable();
            $table->text('advisor_notes')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index(['strategic_plan_id', 'status']);
            $table->index(['strategic_plan_id', 'owner']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('strategic_plan_milestones');
        Schema::dropIfExists('strategic_plans');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['strategic_plans', 'strategic_plan_milestones'] as $table) {
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
