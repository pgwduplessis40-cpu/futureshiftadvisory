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
        Schema::create('project_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('setting_key', 160)->unique();
            $table->string('group_key', 80);
            $table->string('value_type', 40)->default('string');
            $table->boolean('is_secret')->default(false);
            $table->text('value')->nullable();
            $table->text('value_envelope')->nullable();
            $table->jsonb('value_envelope_meta')->nullable();
            $table->string('last_four', 8)->nullable();
            $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('group_key');
            $table->index('set_by_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('project_settings');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE project_settings ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_settings FORCE ROW LEVEL SECURITY;

            CREATE POLICY project_settings_super_admin_scope ON project_settings
                USING (fsa_current_role() IN ('super_admin', 'system'))
                WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));
        SQL);
    }
};
