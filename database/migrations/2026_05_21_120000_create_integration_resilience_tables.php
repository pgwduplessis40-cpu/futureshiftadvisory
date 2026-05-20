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
        Schema::create('integration_calls', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('service', 80);
            $table->string('endpoint', 500);
            $table->uuid('request_id')->nullable();
            $table->string('status', 16);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->jsonb('error_payload')->nullable();
            $table->uuid('correlation_id');
            $table->timestampTz('occurred_at')->default(DB::raw('now()'));

            $table->index(['service', 'occurred_at']);
            $table->index(['service', 'status', 'occurred_at']);
            $table->index('request_id');
            $table->index('correlation_id');
        });

        Schema::create('integration_health_samples', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('service', 80);
            $table->timestampTz('window_start');
            $table->timestampTz('window_end');
            $table->decimal('success_rate', 5, 4);
            $table->unsignedInteger('p95_latency_ms')->nullable();
            $table->string('health', 16);
            $table->timestampsTz();

            $table->unique(['service', 'window_start', 'window_end']);
            $table->index(['health', 'window_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_health_samples');
        Schema::dropIfExists('integration_calls');
    }
};
