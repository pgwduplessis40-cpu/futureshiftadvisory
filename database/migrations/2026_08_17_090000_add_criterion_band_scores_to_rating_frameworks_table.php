<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rating_frameworks', function (Blueprint $table): void {
            $table->jsonb('criterion_band_scores')->nullable()->after('grade_bands');
        });

        DB::table('rating_frameworks')
            ->whereNull('criterion_band_scores')
            ->update([
                'criterion_band_scores' => json_encode([
                    'exceptional' => 100,
                    'strong' => 80,
                    'developing' => 55,
                    'needs_work' => 30,
                ], JSON_THROW_ON_ERROR),
            ]);
    }

    public function down(): void
    {
        Schema::table('rating_frameworks', function (Blueprint $table): void {
            $table->dropColumn('criterion_band_scores');
        });
    }
};
