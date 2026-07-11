<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspiration_rotation_schedule_posts', function (Blueprint $table): void {
            $table->timestampTz('featured_at')->nullable()->after('scheduled_at');
            $table->index('featured_at');
        });
    }

    public function down(): void
    {
        Schema::table('inspiration_rotation_schedule_posts', function (Blueprint $table): void {
            $table->dropIndex(['featured_at']);
            $table->dropColumn('featured_at');
        });
    }
};
