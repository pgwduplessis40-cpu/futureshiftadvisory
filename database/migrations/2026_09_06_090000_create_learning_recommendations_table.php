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
        Schema::create('learning_recommendations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('learning_update_id')->constrained('learning_updates')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('failure_shortfall');
            $table->text('impact');
            $table->string('impact_area', 255);
            $table->text('recommendation');
            $table->text('recommendation_impact');
            $table->jsonb('acceptance_criteria')->nullable();
            $table->jsonb('regression_journeys')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->string('status', 40)->default('draft');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->string('development_reference', 255)->nullable();
            $table->string('release_reference', 255)->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestampTz('review_due_at')->nullable();
            $table->timestampTz('rolled_back_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'approved_at']);
            $table->index(['learning_update_id', 'status']);
            $table->index('review_due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_recommendations');
    }
};
