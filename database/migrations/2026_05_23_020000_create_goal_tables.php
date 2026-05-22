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
        Schema::create('goals', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignUuid('pv_target_calculation_id')->nullable()->constrained('pv_calculations')->nullOnDelete();
            $table->decimal('pv_target', 15, 2)->default(0);
            $table->string('status', 40)->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index('pv_target_calculation_id');
            $table->index('created_by_user_id');
        });

        Schema::create('milestones', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('goal_id')->constrained('goals')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('title');
            $table->string('recommendation_ref')->nullable();
            $table->foreignUuid('pv_of_impact_calculation_id')->nullable()->constrained('pv_calculations')->nullOnDelete();
            $table->decimal('pv_of_impact', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 40)->default('pending');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index(['goal_id', 'status']);
            $table->index('pv_of_impact_calculation_id');
        });

        Schema::create('milestone_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('milestone_id')->constrained('milestones')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('priority', 40)->default('normal');
            $table->string('status', 40)->default('pending');
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index(['milestone_id', 'status']);
            $table->index('owner_user_id');
        });

        Schema::create('proof_of_completion', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('milestone_id')->constrained('milestones')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUuid('document_verification_id')->nullable()->constrained('document_verifications')->nullOnDelete();
            $table->string('status', 40)->default('pending');
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index(['milestone_id', 'status']);
            $table->index('document_verification_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('proof_of_completion');
        Schema::dropIfExists('milestone_actions');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('goals');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['goals', 'milestones', 'milestone_actions', 'proof_of_completion'] as $table) {
            DB::unprepared(<<<SQL
                ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;
                ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;

                CREATE POLICY {$table}_client_scope ON {$table}
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
