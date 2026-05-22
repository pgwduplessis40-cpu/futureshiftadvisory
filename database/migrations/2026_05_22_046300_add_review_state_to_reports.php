<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->string('review_status', 40)->default('not_required')->after('metadata');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable()->after('reviewed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn(['review_status', 'reviewed_at']);
        });
    }
};
