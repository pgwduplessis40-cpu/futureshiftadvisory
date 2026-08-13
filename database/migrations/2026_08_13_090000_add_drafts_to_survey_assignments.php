<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_assignments', function (Blueprint $table): void {
            $table->jsonb('draft_answers')->nullable()->after('deliverable_snapshot');
            $table->timestampTz('draft_saved_at')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('survey_assignments', function (Blueprint $table): void {
            $table->dropColumn(['draft_answers', 'draft_saved_at']);
        });
    }
};
