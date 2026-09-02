<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategic_budgets', function (Blueprint $table): void {
            $table->unsignedInteger('revision')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('strategic_budgets', function (Blueprint $table): void {
            $table->dropColumn('revision');
        });
    }
};
