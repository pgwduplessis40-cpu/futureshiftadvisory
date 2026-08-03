<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_update_implementations', function (Blueprint $table): void {
            $table->jsonb('review_metrics')->nullable()->after('review_outcome');
        });
    }

    public function down(): void
    {
        Schema::table('learning_update_implementations', function (Blueprint $table): void {
            $table->dropColumn('review_metrics');
        });
    }
};
