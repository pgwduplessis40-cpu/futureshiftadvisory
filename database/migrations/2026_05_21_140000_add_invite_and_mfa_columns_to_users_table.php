<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('user_type', 40)->nullable()->after('email_verified_at');
            $table->string('primary_role', 80)->nullable()->after('user_type');
            $table->timestampTz('mfa_enabled_at')->nullable()->after('remember_token');
            $table->string('mfa_method', 24)->nullable()->after('mfa_enabled_at');
            $table->timestampTz('last_password_set_at')->nullable()->after('mfa_method');
            $table->unsignedSmallInteger('session_timeout_minutes')->nullable()->after('last_password_set_at');
            $table->timestampTz('suspended_at')->nullable()->after('session_timeout_minutes');
            $table->string('suspended_reason')->nullable()->after('suspended_at');

            $table->index('user_type');
            $table->index('primary_role');
            $table->index('mfa_enabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['user_type']);
            $table->dropIndex(['primary_role']);
            $table->dropIndex(['mfa_enabled_at']);
            $table->dropColumn([
                'user_type',
                'primary_role',
                'mfa_enabled_at',
                'mfa_method',
                'last_password_set_at',
                'session_timeout_minutes',
                'suspended_at',
                'suspended_reason',
            ]);
        });
    }
};
