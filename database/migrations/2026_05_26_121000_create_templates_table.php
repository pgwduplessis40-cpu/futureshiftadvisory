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
        Schema::create('templates', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('category', 80);
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->jsonb('structure')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('status', 40)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUuid('learning_update_implementation_id')
                ->nullable()
                ->constrained('learning_update_implementations')
                ->nullOnDelete();
            $table->timestampsTz();

            $table->index(['status', 'category']);
            $table->index('source_reference');
            $table->index('learning_update_implementation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
