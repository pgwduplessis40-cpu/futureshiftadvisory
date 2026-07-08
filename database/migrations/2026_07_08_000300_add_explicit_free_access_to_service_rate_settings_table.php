<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_rate_settings', function (Blueprint $table): void {
            $table->boolean('free_access_enabled')->default(false)->after('is_active');
            $table->timestampTz('free_access_enabled_at')->nullable()->after('free_access_enabled');
            $table->foreignId('free_access_enabled_by_user_id')
                ->nullable()
                ->after('free_access_enabled_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->index('free_access_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('service_rate_settings', function (Blueprint $table): void {
            $table->dropForeign(['free_access_enabled_by_user_id']);
            $table->dropIndex(['free_access_enabled']);
            $table->dropColumn([
                'free_access_enabled',
                'free_access_enabled_at',
                'free_access_enabled_by_user_id',
            ]);
        });
    }
};
