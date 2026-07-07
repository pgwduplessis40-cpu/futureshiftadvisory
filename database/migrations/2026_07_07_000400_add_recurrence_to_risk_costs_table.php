<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_costs', function (Blueprint $table): void {
            $table->string('recurrence', 40)->default('recurring');
            $table->unsignedSmallInteger('cash_flow_years')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('risk_costs', function (Blueprint $table): void {
            $table->dropColumn(['recurrence', 'cash_flow_years']);
        });
    }
};
