<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_layer_state', function (Blueprint $table): void {
            $table->unsignedSmallInteger('layer_id')->primary();
            $table->string('name');
            $table->string('cadence', 32);
            $table->boolean('active')->default(false);
            $table->unsignedInteger('min_sample')->default(1);
            $table->timestampTz('last_run_at')->nullable();
            $table->timestampTz('next_due_at')->nullable();
            $table->jsonb('config')->nullable();
            $table->timestampsTz();

            $table->index(['active', 'next_due_at']);
            $table->index('cadence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_layer_state');
    }
};
