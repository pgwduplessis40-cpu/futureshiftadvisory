<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_assessments', function (Blueprint $table): void {
            $table->jsonb('scoring_scope')->nullable()->after('plan_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('plan_assessments', function (Blueprint $table): void {
            $table->dropColumn('scoring_scope');
        });
    }
};
