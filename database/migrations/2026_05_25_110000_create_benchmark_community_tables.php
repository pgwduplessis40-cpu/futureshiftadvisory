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
        Schema::table('consents', function (Blueprint $table): void {
            $table->foreignId('subject_user_id')->nullable()->after('client_id')->constrained('users')->nullOnDelete();
            $table->index(['subject_user_id', 'type', 'election', 'revoked_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE consents ALTER COLUMN client_id DROP NOT NULL');
        }

        Schema::create('benchmark_aggregates', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('domain', 40);
            $table->string('industry_code', 120);
            $table->string('metric', 80);
            $table->jsonb('distribution');
            $table->unsignedInteger('cohort_size');
            $table->string('quarter', 12);
            $table->boolean('suppressed')->default(false);
            $table->timestampTz('generated_at');
            $table->timestampTz('privacy_counsel_signed_off_at')->nullable();
            $table->foreignId('privacy_counsel_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['domain', 'industry_code', 'metric', 'quarter']);
            $table->index(['domain', 'industry_code', 'quarter']);
            $table->index(['suppressed', 'generated_at']);
        });

        Schema::create('peer_network_members', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('community', 40);
            $table->string('pseudonym', 80);
            $table->timestampTz('joined_at');
            $table->foreignUuid('consent_id')->constrained('consents')->cascadeOnDelete();
            $table->string('status', 40)->default('active');
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'community']);
            $table->unique(['community', 'pseudonym']);
            $table->index(['community', 'status']);
            $table->index('consent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_network_members');
        Schema::dropIfExists('benchmark_aggregates');

        Schema::table('consents', function (Blueprint $table): void {
            $table->dropIndex(['subject_user_id', 'type', 'election', 'revoked_at']);
            $table->dropConstrainedForeignId('subject_user_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE consents ALTER COLUMN client_id SET NOT NULL');
        }
    }
};
