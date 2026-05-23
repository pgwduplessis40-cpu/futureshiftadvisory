<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_sections', function (Blueprint $table): void {
            $table->jsonb('attached_document_ids')->nullable()->after('body');
            $table->jsonb('predictive_score')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('plan_sections', function (Blueprint $table): void {
            $table->dropColumn(['attached_document_ids', 'predictive_score']);
        });
    }
};
