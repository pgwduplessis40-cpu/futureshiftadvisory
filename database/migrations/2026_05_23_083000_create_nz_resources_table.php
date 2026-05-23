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
        Schema::create('nz_resources', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('industry', 80)->default('general');
            $table->string('business_type', 80)->default('general');
            $table->string('title');
            $table->string('url');
            $table->jsonb('gap_tags');
            $table->jsonb('metadata')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->index(['industry', 'business_type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nz_resources');
    }
};
