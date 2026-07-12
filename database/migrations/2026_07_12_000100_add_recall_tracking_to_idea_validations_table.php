<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idea_validations', function (Blueprint $table): void {
            $table->timestampTz('recalled_at')->nullable()->after('advisor_gate_note');
            $table->foreignId('recalled_by_user_id')
                ->nullable()
                ->after('recalled_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->index('recalled_at');
        });
    }

    public function down(): void
    {
        Schema::table('idea_validations', function (Blueprint $table): void {
            $table->dropForeign(['recalled_by_user_id']);
            $table->dropIndex(['recalled_at']);
            $table->dropColumn(['recalled_at', 'recalled_by_user_id']);
        });
    }
};
