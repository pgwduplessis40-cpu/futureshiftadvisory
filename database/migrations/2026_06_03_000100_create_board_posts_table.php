<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('type', 16);
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('attribution')->nullable();

            // Image provenance lives in the scanned/encrypted documents pipeline; the
            // serving path/mime are denormalised here so the global board can stream
            // images without a client-scoped documents query.
            $table->foreignUuid('image_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('image_path', 600)->nullable();
            $table->string('image_mime', 150)->nullable();
            $table->string('image_filename')->nullable();

            $table->string('status', 16)->default('draft');
            $table->boolean('pinned')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['status', 'pinned']);
            $table->index('published_at');
            $table->index('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_posts');
    }
};
