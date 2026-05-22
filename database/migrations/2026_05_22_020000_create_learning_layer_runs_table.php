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
        Schema::create('learning_layer_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->unsignedSmallInteger('layer_id');
            $table->timestampTz('ran_at');
            $table->unsignedInteger('candidates_created')->default(0);
            $table->jsonb('window');
            $table->string('status', 32);
            $table->timestampsTz();

            $table->index(['layer_id', 'ran_at']);
            $table->index(['layer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_layer_runs');
    }
};
