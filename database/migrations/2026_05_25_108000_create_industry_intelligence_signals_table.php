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
        Schema::create('industry_intelligence_signals', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('industry_code', 120);
            $table->string('signal_type', 80);
            $table->string('signal_key', 128)->unique();
            $table->jsonb('aggregate');
            $table->unsignedInteger('cohort_size');
            $table->timestampTz('generated_at');
            $table->boolean('suppressed')->default(false);
            $table->timestampTz('alerted_at')->nullable();
            $table->timestampsTz();

            $table->index(['industry_code', 'signal_type']);
            $table->index(['suppressed', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('industry_intelligence_signals');
    }
};
