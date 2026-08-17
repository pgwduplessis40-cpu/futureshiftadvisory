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
            $table->unsignedSmallInteger('assessment_run_total_criteria')->nullable()->after('assessment_run_started_at');
            $table->unsignedSmallInteger('assessment_run_completed_criteria')->nullable()->after('assessment_run_total_criteria');
            $table->string('assessment_run_current_criterion')->nullable()->after('assessment_run_completed_criteria');
        });
    }

    public function down(): void
    {
        Schema::table('business_plans', function (Blueprint $table): void {
            $table->dropColumn([
                'assessment_run_total_criteria',
                'assessment_run_completed_criteria',
                'assessment_run_current_criterion',
            ]);
        });
    }
};
