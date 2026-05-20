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
        Schema::create('learning_updates', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->unsignedSmallInteger('layer_id');
            $table->jsonb('source');
            $table->text('summary');
            $table->jsonb('proposed_change')->nullable();
            $table->jsonb('impact_scope')->nullable();
            $table->unsignedInteger('clients_affected')->default(0);
            $table->string('magnitude', 32)->default('low');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->jsonb('evidence')->nullable();
            $table->timestampTz('effective_date')->nullable();
            $table->string('status', 32)->default('detected');
            $table->foreignId('decided_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->uuid('rollback_id')->nullable();
            $table->timestampsTz();

            $table->index(['layer_id', 'status']);
            $table->index('effective_date');
            $table->index('rollback_id');
        });

        Schema::create('learning_update_implementations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('learning_update_id')
                ->constrained('learning_updates')
                ->cascadeOnDelete();
            $table->timestampTz('implemented_at')->nullable();
            $table->timestampTz('review_due')->nullable();
            $table->text('review_outcome')->nullable();
            $table->timestampTz('rolled_back_at')->nullable();
            $table->timestampsTz();

            $table->index(['implemented_at', 'review_due']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_update_implementations');
        Schema::dropIfExists('learning_updates');
    }
};
