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
        Schema::create('shared_intelligence_patterns', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('source_domain', 40);
            $table->string('target_domain', 40);
            $table->string('signal_key', 128)->unique();
            $table->jsonb('pattern');
            $table->unsignedInteger('cohort_size');
            $table->timestampTz('generated_at');
            $table->timestampsTz();

            $table->index(['source_domain', 'target_domain']);
            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_intelligence_patterns');
    }
};
