<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms_versions', function (Blueprint $table): void {
            $table->string('document_scope', 30)->default('proposal');
            $table->dropUnique('terms_versions_version_unique');
            $table->unique(['document_scope', 'version']);
            $table->index(['document_scope', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('terms_versions', function (Blueprint $table): void {
            $table->dropIndex('terms_versions_document_scope_published_at_index');
            $table->dropUnique('terms_versions_document_scope_version_unique');
            $table->unique('version');
            $table->dropColumn('document_scope');
        });
    }
};
