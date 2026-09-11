<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_recommendations', function (Blueprint $table): void {
            $table->string('delivery_owner', 160)->nullable()->after('approved_at');
            $table->string('delivery_target', 255)->nullable()->after('delivery_owner');
            $table->jsonb('baseline_metrics')->nullable()->after('regression_journeys');
            $table->text('rollback_plan')->nullable()->after('baseline_metrics');
        });
    }

    public function down(): void
    {
        Schema::table('learning_recommendations', function (Blueprint $table): void {
            $table->dropColumn([
                'delivery_owner',
                'delivery_target',
                'baseline_metrics',
                'rollback_plan',
            ]);
        });
    }
};
