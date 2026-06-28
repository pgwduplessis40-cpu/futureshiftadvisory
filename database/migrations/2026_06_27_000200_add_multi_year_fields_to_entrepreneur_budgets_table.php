<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrepreneur_budgets', function (Blueprint $table): void {
            $table->unsignedTinyInteger('forecast_years')->default(3)->after('expected_runway_months');
            $table->jsonb('assumptions')->nullable()->after('status');
            $table->jsonb('future_costs')->nullable()->after('monthly_fixed_costs');
            $table->jsonb('funding_scenarios')->nullable()->after('funding_sources');
        });
    }

    public function down(): void
    {
        Schema::table('entrepreneur_budgets', function (Blueprint $table): void {
            $table->dropColumn([
                'forecast_years',
                'assumptions',
                'future_costs',
                'funding_scenarios',
            ]);
        });
    }
};
