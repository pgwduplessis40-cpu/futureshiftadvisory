<?php

declare(strict_types=1);

use App\Models\IntegrationCredential;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_credentials', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('integration_key', 80);
            $table->string('field', 80);
            $table->text('value_envelope')->nullable();
            $table->jsonb('value_envelope_meta')->nullable();
            $table->string('last_four', 8)->nullable();
            $table->string('status', 16)->default(IntegrationCredential::STATUS_ACTIVE);
            $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('rotated_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->unique(['integration_key', 'field']);
            $table->index(['integration_key', 'status']);
            $table->index('set_by_user_id');
        });

        $this->installConstraintsAndPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_credentials');
    }

    private function installConstraintsAndPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(
            <<<'SQL'
                ALTER TABLE integration_credentials
                    ADD CONSTRAINT integration_credentials_status_check CHECK (status IN ('active', 'revoked'));

                ALTER TABLE integration_credentials ENABLE ROW LEVEL SECURITY;
                ALTER TABLE integration_credentials FORCE ROW LEVEL SECURITY;

                CREATE POLICY integration_credentials_super_admin_scope ON integration_credentials
                    USING (fsa_current_role() IN ('super_admin', 'system'))
                    WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));
            SQL
        );
    }
};
