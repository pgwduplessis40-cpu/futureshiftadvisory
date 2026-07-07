<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_valuations', function (Blueprint $table): void {
            $table->jsonb('method_weights')->nullable()->after('dcf_value');
            $table->jsonb('method_rationale')->nullable()->after('method_weights');
            $table->jsonb('valuation_disclosures')->nullable()->after('data_quality_disclaimer');
            $table->jsonb('equity_bridge')->nullable()->after('valuation_disclosures');
            $table->jsonb('dcf_sensitivity')->nullable()->after('equity_bridge');
            $table->jsonb('succession_comparison')->nullable()->after('dcf_sensitivity');
        });
    }

    public function down(): void
    {
        Schema::table('business_valuations', function (Blueprint $table): void {
            $table->dropColumn([
                'method_weights',
                'method_rationale',
                'valuation_disclosures',
                'equity_bridge',
                'dcf_sensitivity',
                'succession_comparison',
            ]);
        });
    }
};
