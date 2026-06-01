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
        Schema::create('integration_activations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('integration_key', 80)->unique();
            $table->boolean('active')->default(false);
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('deactivated_at')->nullable();
            $table->timestampsTz();

            $table->index(['active', 'updated_at']);
        });

        $this->installPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_activations');
    }

    private function installPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(
            <<<'SQL'
                ALTER TABLE integration_activations ENABLE ROW LEVEL SECURITY;
                ALTER TABLE integration_activations FORCE ROW LEVEL SECURITY;

                CREATE POLICY integration_activations_super_admin_scope ON integration_activations
                    USING (fsa_current_role() IN ('super_admin', 'system'))
                    WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));
            SQL
        );
    }
};
