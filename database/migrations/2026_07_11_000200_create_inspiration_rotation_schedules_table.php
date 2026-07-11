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
        Schema::table('board_posts', function (Blueprint $table): void {
            $table->timestampTz('featured_at')->nullable()->after('scheduled_at');
            $table->string('featured_source', 24)->nullable()->after('featured_at');
            $table->index(['featured_at', 'featured_source']);
        });

        Schema::create('inspiration_rotation_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name', 120);
            $table->string('status', 16)->default('scheduled');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->unsignedSmallInteger('cadence_days');
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index('created_by_user_id');
        });

        Schema::create('inspiration_rotation_schedule_posts', function (Blueprint $table): void {
            $table->foreignUuid('inspiration_rotation_schedule_id')
                ->constrained('inspiration_rotation_schedules')
                ->cascadeOnDelete();
            $table->foreignUuid('board_post_id')->constrained('board_posts')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->timestampTz('scheduled_at');

            $table->primary(['inspiration_rotation_schedule_id', 'board_post_id']);
            $table->index(['inspiration_rotation_schedule_id', 'position']);
            $table->index('board_post_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE inspiration_rotation_schedules
                    ADD CONSTRAINT inspiration_rotation_schedule_windows_no_overlap
                    EXCLUDE USING gist (
                        tstzrange(starts_at, ends_at, '[]') WITH &&
                    )
                    WHERE (status = 'scheduled');
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inspiration_rotation_schedule_posts');
        Schema::dropIfExists('inspiration_rotation_schedules');

        Schema::table('board_posts', function (Blueprint $table): void {
            $table->dropIndex(['featured_at', 'featured_source']);
            $table->dropColumn(['featured_at', 'featured_source']);
        });
    }
};
