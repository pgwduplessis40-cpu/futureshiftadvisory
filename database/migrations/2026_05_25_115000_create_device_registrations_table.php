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
        Schema::create('device_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_id', 120);
            $table->string('platform', 40);
            $table->string('device_name')->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 24)->default('active');
            $table->jsonb('capabilities')->nullable();
            $table->timestampTz('mfa_confirmed_at')->nullable();
            $table->timestampTz('terms_confirmed_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['user_id', 'status']);
            $table->index(['device_id', 'platform']);
            $table->index('expires_at');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE device_registrations ENABLE ROW LEVEL SECURITY;
            ALTER TABLE device_registrations FORCE ROW LEVEL SECURITY;
            CREATE POLICY device_registrations_user_scope ON device_registrations
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR user_id::text = fsa_current_user_id()
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR user_id::text = fsa_current_user_id()
                );
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('device_registrations');
    }
};
