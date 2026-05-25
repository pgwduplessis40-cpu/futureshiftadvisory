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
        Schema::create('crypto_rotations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('run_id');
            $table->string('rotation_type', 64);
            $table->string('source_table', 80)->nullable();
            $table->string('source_column', 80)->nullable();
            $table->string('source_id')->nullable();
            $table->unsignedSmallInteger('from_version')->nullable();
            $table->string('from_alg', 80)->nullable();
            $table->string('from_kid')->nullable();
            $table->unsignedSmallInteger('to_version');
            $table->string('to_alg', 80);
            $table->string('to_kid')->nullable();
            $table->jsonb('from_meta')->nullable();
            $table->jsonb('to_meta')->nullable();
            $table->string('status', 32);
            $table->string('idempotency_key')->unique();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['rotation_type', 'status']);
            $table->index(['source_table', 'source_column']);
            $table->index('run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_rotations');
    }
};
