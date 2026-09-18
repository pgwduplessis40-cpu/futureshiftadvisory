<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('entrepreneur_budgets', 'revision')) {
            return;
        }

        Schema::table('entrepreneur_budgets', function (Blueprint $table): void {
            $table->unsignedInteger('revision')->default(1);
        });
    }

    public function down(): void
    {
        // The original revision migration owns the column for full rollbacks.
    }
};
