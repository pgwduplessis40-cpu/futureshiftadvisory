<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_posts', function (Blueprint $table): void {
            $table->timestampTz('scheduled_at')->nullable()->after('published_at');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('board_posts', function (Blueprint $table): void {
            $table->dropIndex(['scheduled_at']);
            $table->dropColumn('scheduled_at');
        });
    }
};
