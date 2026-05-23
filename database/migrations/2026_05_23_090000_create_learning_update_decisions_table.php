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
        Schema::table('learning_updates', function (Blueprint $table): void {
            $table->timestampTz('pre_implementation_notice_at')->nullable()->after('effective_date');
            $table->timestampTz('review_due_at')->nullable()->after('pre_implementation_notice_at');

            $table->index('review_due_at');
        });

        Schema::create('learning_update_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('learning_update_id')
                ->constrained('learning_updates')
                ->cascadeOnDelete();
            $table->string('decision', 40);
            $table->timestampTz('effective_date')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('decided_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('decided_at');
            $table->timestampsTz();

            $table->index(['learning_update_id', 'decided_at']);
            $table->index(['decision', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_update_decisions');

        Schema::table('learning_updates', function (Blueprint $table): void {
            $table->dropIndex(['review_due_at']);
            $table->dropColumn(['pre_implementation_notice_at', 'review_due_at']);
        });
    }
};
