<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table): void {
            $table->unsignedSmallInteger('pv_remeasurement_failure_count')->default(0)->after('achieved_by_user_id');
            $table->timestampTz('pv_remeasurement_failed_at')->nullable()->after('pv_remeasurement_failure_count');
            $table->timestampTz('pv_remeasurement_next_retry_at')->nullable()->after('pv_remeasurement_failed_at');
            $table->text('pv_remeasurement_failure_reason')->nullable()->after('pv_remeasurement_next_retry_at');
            $table->index('pv_remeasurement_next_retry_at', 'goals_pv_remeasurement_retry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table): void {
            $table->dropIndex('goals_pv_remeasurement_retry_idx');
            $table->dropColumn([
                'pv_remeasurement_failure_count',
                'pv_remeasurement_failed_at',
                'pv_remeasurement_next_retry_at',
                'pv_remeasurement_failure_reason',
            ]);
        });
    }
};
