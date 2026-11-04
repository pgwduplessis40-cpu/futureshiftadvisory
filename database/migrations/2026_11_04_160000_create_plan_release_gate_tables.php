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
        Schema::create('plan_gate_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_plan_id')->constrained('business_plans')->restrictOnDelete();
            $table->string('document_kind', 16);
            $table->string('audience', 16);
            foreach (['source_hash', 'inputs_hash', 'context_hash', 'configuration_hash'] as $name) {
                $table->string($name, 64);
            }
            $table->unsignedInteger('required_horizon');
            $table->jsonb('source_payload');
            $table->jsonb('result_payload');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');
            $table->index(['business_plan_id', 'created_at']);
            $table->index(['business_plan_id', 'context_hash', 'configuration_hash']);
        });
        Schema::create('plan_rule_config_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('configuration_hash', 64);
            $table->jsonb('configuration');
            $table->text('reason');
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');
        });
        Schema::create('plan_rule_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_plan_id')->constrained('business_plans')->restrictOnDelete();
            $table->foreignUuid('plan_gate_run_id')->constrained('plan_gate_runs')->restrictOnDelete();
            $table->string('rule_id', 8);
            $table->string('subcheck_id', 180);
            foreach (['finding_hash', 'dependency_hash', 'configuration_hash'] as $name) {
                $table->string($name, 64);
            }
            $table->string('action', 16);
            $table->uuid('supersedes_id')->nullable();
            $table->text('reason');
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');
            $table->index(['business_plan_id', 'rule_id', 'created_at']);
        });
        Schema::table('plan_rule_decisions', function (Blueprint $table): void {
            $table->foreign('supersedes_id')->references('id')->on('plan_rule_decisions')->restrictOnDelete();
        });
        Schema::create('plan_claim_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_plan_id')->constrained('business_plans')->restrictOnDelete();
            $table->string('claim_key', 160);
            $table->unsignedInteger('revision');
            $table->string('state', 16);
            $table->string('source_hash', 64);
            $table->jsonb('fact');
            $table->text('reason');
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');
            $table->unique(['business_plan_id', 'claim_key', 'revision']);
        });
        foreach (['entrepreneurs.release_policy', 'entrepreneurs.accept_release_risk'] as $permission) {
            DB::table('permissions')->insertOrIgnore(['name' => $permission, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->protectRecords();
        }
    }

    private function protectRecords(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_plan_gate_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'release-gate records are append-only';
            END;
            $$;
            CREATE OR REPLACE FUNCTION fsa_release_permission(permission_name text) RETURNS boolean LANGUAGE sql STABLE AS $$
                SELECT fsa_current_role() IN ('super_admin', 'system') OR EXISTS (
                    SELECT 1 FROM permissions p WHERE p.name = permission_name AND p.guard_name = 'web' AND (
                        EXISTS (SELECT 1 FROM model_has_permissions m WHERE m.permission_id = p.id
                            AND m.model_id::text = fsa_current_user_id() AND m.model_type = 'App\Models\User')
                        OR EXISTS (SELECT 1 FROM model_has_roles m JOIN role_has_permissions r ON r.role_id = m.role_id
                            WHERE r.permission_id = p.id AND m.model_id::text = fsa_current_user_id() AND m.model_type = 'App\Models\User')
                    )
                );
            $$;
        SQL);
        foreach (['plan_gate_runs', 'plan_rule_config_versions', 'plan_rule_decisions', 'plan_claim_versions'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION reject_plan_gate_mutation()");
            DB::unprepared("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::unprepared("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            $scope = $table === 'plan_rule_config_versions'
                ? "fsa_current_role() IN ('super_admin', 'system', 'advisor', 'junior_advisor', 'entrepreneur', 'client_primary')"
                : "fsa_current_role() IN ('super_admin', 'system') OR EXISTS (
                    SELECT 1 FROM business_plans p JOIN entrepreneur_profiles e ON e.id = p.entrepreneur_profile_id
                    WHERE p.id = {$table}.business_plan_id AND (
                        e.assigned_advisor_id::text = fsa_current_user_id() OR e.user_id::text = fsa_current_user_id()
                    ))";
            $authority = match ($table) {
                'plan_rule_config_versions' => "fsa_release_permission('entrepreneurs.release_policy')",
                'plan_rule_decisions' => "fsa_release_permission('entrepreneurs.accept_release_risk')",
                'plan_claim_versions' => "fsa_release_permission('entrepreneurs.assess')",
                default => 'TRUE',
            };
            $write = "({$scope}) AND ({$authority}) AND (fsa_current_role() IN ('super_admin', 'system') OR actor_user_id::text = fsa_current_user_id())";
            DB::unprepared("CREATE POLICY {$table}_scope ON {$table} USING ({$scope}) WITH CHECK ({$write})");
        }
    }

    public function down(): void
    {
        foreach (['plan_claim_versions', 'plan_rule_decisions', 'plan_rule_config_versions', 'plan_gate_runs'] as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS reject_plan_gate_mutation()');
            DB::unprepared('DROP FUNCTION IF EXISTS fsa_release_permission(text)');
        }
    }
};
