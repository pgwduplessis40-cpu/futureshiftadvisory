<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategic_budgets', function (Blueprint $table): void {
            $table->jsonb('business_plan_sections')->default('[]');
            $table->jsonb('business_plan_source_drafts')->default('[]');
            $table->jsonb('business_plan_prompts')->default('[]');
            $table->timestampTz('business_plan_submitted_at')->nullable();
            $table->timestampTz('business_plan_approved_at')->nullable();
            $table->foreignId('business_plan_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('strategic_budgets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_plan_approved_by_user_id');
            $table->dropColumn([
                'business_plan_sections',
                'business_plan_source_drafts',
                'business_plan_prompts',
                'business_plan_submitted_at',
                'business_plan_approved_at',
            ]);
        });
    }
};
