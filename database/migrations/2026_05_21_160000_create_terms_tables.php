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
        Schema::create('terms_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('version', 40)->unique();
            $table->string('title')->default('Future Shift Advisory Terms and Conditions');
            $table->boolean('material')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('notice_period_days')->default(30);
            $table->string('reviewer_reference')->nullable();
            $table->string('pdf_path', 600)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['published_at', 'material']);
            $table->index('created_by_user_id');
        });

        Schema::create('terms_clauses', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('terms_version_id')->constrained('terms_versions')->cascadeOnDelete();
            $table->unsignedSmallInteger('clause_number');
            $table->string('title');
            $table->longText('body');
            $table->boolean('material')->default(false);
            $table->timestampsTz();

            $table->unique(['terms_version_id', 'clause_number']);
            $table->index(['terms_version_id', 'material']);
        });

        Schema::create('terms_acceptances', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('terms_version_id')->constrained('terms_versions')->restrictOnDelete();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('declined_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('reacceptance_notice_queued_at')->nullable();
            $table->string('signed_pdf_path', 600)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'terms_version_id']);
            $table->index(['accepted_at', 'declined_at', 'expires_at']);
            $table->index('reacceptance_notice_queued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_acceptances');
        Schema::dropIfExists('terms_clauses');
        Schema::dropIfExists('terms_versions');
    }
};
