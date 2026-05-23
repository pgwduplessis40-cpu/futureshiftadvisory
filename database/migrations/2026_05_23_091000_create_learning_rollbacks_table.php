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
        Schema::table('learning_update_implementations', function (Blueprint $table): void {
            $table->string('target_type')->nullable()->after('review_outcome');
            $table->string('target_id')->nullable()->after('target_type');
            $table->jsonb('before_state')->nullable()->after('target_id');
            $table->jsonb('after_state')->nullable()->after('before_state');

            $table->index(['target_type', 'target_id']);
        });

        Schema::create('learning_rollbacks', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('learning_update_id')
                ->constrained('learning_updates')
                ->cascadeOnDelete();
            $table->foreignUuid('learning_update_implementation_id')
                ->unique()
                ->constrained('learning_update_implementations')
                ->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('rolled_back_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('rolled_back_at');
            $table->jsonb('restored_state');
            $table->timestampsTz();

            $table->index(['learning_update_id', 'rolled_back_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_rollbacks');

        Schema::table('learning_update_implementations', function (Blueprint $table): void {
            $table->dropIndex(['target_type', 'target_id']);
            $table->dropColumn(['target_type', 'target_id', 'before_state', 'after_state']);
        });
    }
};
