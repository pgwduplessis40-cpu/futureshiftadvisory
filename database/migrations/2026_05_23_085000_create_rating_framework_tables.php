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
        Schema::create('rating_frameworks', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->unsignedInteger('version');
            $table->string('status', 40)->default('draft');
            $table->string('industry_variant', 80)->nullable();
            $table->boolean('production_ready')->default(false);
            $table->jsonb('grade_bands');
            $table->uuid('supersedes_framework_id')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['version', 'industry_variant']);
            $table->index(['status', 'industry_variant']);
            $table->index('production_ready');
            $table->index('supersedes_framework_id');
        });

        Schema::create('rating_criteria', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('rating_framework_id')->constrained('rating_frameworks')->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->string('name');
            $table->decimal('weight', 6, 3)->default(0);
            $table->jsonb('descriptors');
            $table->jsonb('industry_variants')->nullable();
            $table->boolean('is_placeholder')->default(true);
            $table->timestampsTz();

            $table->unique(['rating_framework_id', 'number']);
            $table->index('is_placeholder');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_criteria');
        Schema::dropIfExists('rating_frameworks');
    }
};
