<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategic_plans', function (Blueprint $table): void {
            $table->unsignedSmallInteger('duration_months')->default(12)->after('status');
            $table->string('complexity_band', 40)->default('standard')->after('duration_months');
            $table->jsonb('duration_rationale')->nullable()->after('complexity_band');
        });
    }

    public function down(): void
    {
        Schema::table('strategic_plans', function (Blueprint $table): void {
            $table->dropColumn(['duration_months', 'complexity_band', 'duration_rationale']);
        });
    }
};
