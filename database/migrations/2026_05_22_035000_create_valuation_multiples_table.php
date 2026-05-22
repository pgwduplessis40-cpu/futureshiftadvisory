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
        Schema::create('valuation_multiples', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('industry_code', 40);
            $table->string('industry_label');
            $table->string('metric', 24);
            $table->decimal('multiple_low', 8, 2);
            $table->decimal('multiple_mid', 8, 2);
            $table->decimal('multiple_high', 8, 2);
            $table->string('source', 80);
            $table->string('source_badge', 32);
            $table->boolean('degraded')->default(false);
            $table->uuid('correlation_id')->nullable();
            $table->string('quarter', 12);
            $table->timestampTz('fetched_at');
            $table->timestampTz('superseded_at')->nullable();
            $table->string('record_hash', 128)->unique();
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();

            $table->index(['industry_code', 'metric', 'superseded_at']);
            $table->index(['source', 'quarter']);
            $table->index(['metric', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valuation_multiples');
    }
};
