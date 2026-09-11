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
            $table->jsonb('evidence_bindings')->nullable()->after('sections');
            $table->jsonb('source_snapshots')->nullable()->after('evidence_bindings');
        });

        Schema::table('strategic_plan_milestones', function (Blueprint $table): void {
            $table->string('metric_label', 160)->nullable()->after('advisor_notes');
            $table->string('measurement_unit', 80)->nullable()->after('metric_label');
            $table->string('target_direction', 20)->nullable()->after('measurement_unit');
            $table->decimal('baseline_value', 16, 2)->nullable()->after('target_direction');
            $table->decimal('target_value', 16, 2)->nullable()->after('baseline_value');
            $table->decimal('actual_value', 16, 2)->nullable()->after('target_value');
            $table->timestampTz('measurement_updated_at')->nullable()->after('actual_value');
        });
    }

    public function down(): void
    {
        Schema::table('strategic_plan_milestones', function (Blueprint $table): void {
            $table->dropColumn([
                'metric_label',
                'measurement_unit',
                'target_direction',
                'baseline_value',
                'target_value',
                'actual_value',
                'measurement_updated_at',
            ]);
        });

        Schema::table('strategic_plans', function (Blueprint $table): void {
            $table->dropColumn(['evidence_bindings', 'source_snapshots']);
        });
    }
};
