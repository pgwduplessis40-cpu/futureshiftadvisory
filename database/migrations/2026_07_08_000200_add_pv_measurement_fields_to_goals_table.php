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
            $table->foreignUuid('baseline_business_valuation_id')
                ->nullable()
                ->after('pv_target_calculation_id')
                ->constrained('business_valuations')
                ->nullOnDelete();
            $table->foreignUuid('latest_business_valuation_id')
                ->nullable()
                ->after('baseline_business_valuation_id')
                ->constrained('business_valuations')
                ->nullOnDelete();
            $table->date('target_date')->nullable()->after('pv_target');
            $table->decimal('target_growth_percent', 8, 4)->nullable()->after('target_date');
            $table->timestampTz('achieved_at')->nullable()->after('status');
            $table->foreignId('achieved_by_user_id')
                ->nullable()
                ->after('achieved_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('baseline_business_valuation_id', 'goals_baseline_valuation_idx');
            $table->index('latest_business_valuation_id', 'goals_latest_valuation_idx');
            $table->index(['status', 'target_date'], 'goals_status_target_date_idx');
            $table->index('achieved_by_user_id', 'goals_achieved_by_idx');
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table): void {
            $table->dropForeign(['baseline_business_valuation_id']);
            $table->dropForeign(['latest_business_valuation_id']);
            $table->dropForeign(['achieved_by_user_id']);
            $table->dropIndex('goals_baseline_valuation_idx');
            $table->dropIndex('goals_latest_valuation_idx');
            $table->dropIndex('goals_status_target_date_idx');
            $table->dropIndex('goals_achieved_by_idx');
            $table->dropColumn([
                'baseline_business_valuation_id',
                'latest_business_valuation_id',
                'target_date',
                'target_growth_percent',
                'achieved_at',
                'achieved_by_user_id',
            ]);
        });
    }
};
