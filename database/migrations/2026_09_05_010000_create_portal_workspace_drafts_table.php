<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_workspace_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('draft_key', 160);
            $table->jsonb('payload');
            $table->timestampTz('saved_at');
            $table->timestampsTz();

            $table->unique(['user_id', 'draft_key']);
            $table->index(['client_id', 'saved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_workspace_drafts');
    }
};
