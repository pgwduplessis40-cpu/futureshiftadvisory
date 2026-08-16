<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_plans', function (Blueprint $table): void {
            $table->string('assessment_run_status', 24)->nullable()->after('status');
            $table->timestampTz('assessment_run_requested_at')->nullable()->after('assessment_run_status');
            $table->timestampTz('assessment_run_started_at')->nullable()->after('assessment_run_requested_at');
            $table->timestampTz('assessment_run_completed_at')->nullable()->after('assessment_run_started_at');
            $table->timestampTz('assessment_run_failed_at')->nullable()->after('assessment_run_completed_at');
            $table->text('assessment_run_failure')->nullable()->after('assessment_run_failed_at');
            $table->foreignId('assessment_run_requested_by_user_id')->nullable()->after('assessment_run_failure')->constrained('users')->nullOnDelete();
            $table->index(['assessment_run_status', 'assessment_run_requested_at']);
        });
    }

    public function down(): void
    {
        Schema::table('business_plans', function (Blueprint $table): void {
            $table->dropIndex(['assessment_run_status', 'assessment_run_requested_at']);
            $table->dropConstrainedForeignId('assessment_run_requested_by_user_id');
            $table->dropColumn([
                'assessment_run_status',
                'assessment_run_requested_at',
                'assessment_run_started_at',
                'assessment_run_completed_at',
                'assessment_run_failed_at',
                'assessment_run_failure',
            ]);
        });
    }
};
