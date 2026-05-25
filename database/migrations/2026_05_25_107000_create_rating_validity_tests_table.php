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
        Schema::create('rating_validity_tests', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('rating_framework_id')->constrained('rating_frameworks')->cascadeOnDelete();
            $table->string('period', 20);
            $table->jsonb('correlation');
            $table->timestampTz('tested_at');
            $table->timestampsTz();

            $table->unique(['rating_framework_id', 'period']);
            $table->index('tested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_validity_tests');
    }
};
