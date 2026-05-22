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
        Schema::create('economic_indicators', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('indicator', 80);
            $table->string('label');
            $table->decimal('value', 14, 4);
            $table->string('unit', 32);
            $table->date('period_date');
            $table->string('source', 80);
            $table->string('source_badge', 32);
            $table->boolean('degraded')->default(false);
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('fetched_at');
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();

            $table->unique(['indicator', 'period_date', 'source']);
            $table->index(['indicator', 'fetched_at']);
            $table->index(['source', 'fetched_at']);
        });

        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('base_currency', 3);
            $table->string('quote_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->date('rate_date');
            $table->string('source', 80);
            $table->string('source_badge', 32);
            $table->boolean('degraded')->default(false);
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('fetched_at');
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();

            $table->unique(['base_currency', 'quote_currency', 'rate_date', 'source']);
            $table->index(['base_currency', 'quote_currency', 'fetched_at']);
            $table->index(['source', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('economic_indicators');
    }
};
