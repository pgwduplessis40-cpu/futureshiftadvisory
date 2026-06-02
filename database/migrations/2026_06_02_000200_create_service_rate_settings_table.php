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
        Schema::create('service_rate_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->decimal('hourly_rate', 12, 2);
            $table->string('currency', 3)->default('NZD');
            $table->decimal('npo_service_discount_percent', 5, 2)->default(30);
            $table->decimal('npo_retainer_discount_percent', 5, 2)->default(35);
            $table->timestampTz('effective_from')->useCurrent();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['effective_from', 'created_at']);
            $table->index('created_by_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('service_rate_settings');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE service_rate_settings ENABLE ROW LEVEL SECURITY;
            ALTER TABLE service_rate_settings FORCE ROW LEVEL SECURITY;

            CREATE POLICY service_rate_settings_super_admin_scope ON service_rate_settings
                USING (fsa_current_role() IN ('super_admin', 'system'))
                WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));
        SQL);
    }
};
