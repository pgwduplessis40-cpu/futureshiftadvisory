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
        Schema::create('ai_usage_events', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('provider', 40);
            $table->string('task', 80);
            $table->string('model', 120);
            $table->string('prompt_version', 80)->nullable();
            $table->string('prompt_hash', 80)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_creation_input_tokens')->default(0);
            $table->unsignedInteger('cache_read_input_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 14, 8)->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at')->default(DB::raw('now()'));

            $table->index(['provider', 'occurred_at']);
            $table->index(['provider', 'model', 'occurred_at']);
            $table->index(['task', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_events');
    }
};
