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
        Schema::create('operational_health_check_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('status', 16);
            $table->string('environment', 40);
            $table->string('release_version', 40)->nullable();
            $table->string('app_url', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedSmallInteger('total_checks')->default(0);
            $table->unsignedSmallInteger('passed_checks')->default(0);
            $table->unsignedSmallInteger('warning_checks')->default(0);
            $table->unsignedSmallInteger('failed_checks')->default(0);
            $table->unsignedSmallInteger('skipped_checks')->default(0);
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'started_at']);
            $table->index('started_at');
        });

        Schema::create('operational_health_check_results', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('run_id')
                ->constrained('operational_health_check_runs')
                ->cascadeOnDelete();
            $table->string('check_key', 120);
            $table->string('name', 180);
            $table->string('area', 80);
            $table->string('status', 16);
            $table->string('method', 10)->default('GET');
            $table->string('url', 1000)->nullable();
            $table->string('route_name', 190)->nullable();
            $table->jsonb('expected_statuses');
            $table->unsignedSmallInteger('actual_status')->nullable();
            $table->string('expected_content_type', 120)->nullable();
            $table->string('actual_content_type', 190)->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('actor_role', 80)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label', 255)->nullable();
            $table->string('workflow_subject_type', 80)->nullable();
            $table->string('workflow_subject_id', 120)->nullable();
            $table->string('workflow_subject_label', 255)->nullable();
            $table->text('expected_behavior')->nullable();
            $table->string('issue_summary', 500)->nullable();
            $table->text('issue_detail')->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->nullable();
            $table->unsignedSmallInteger('failures_last_7_days')->nullable();
            $table->unsignedSmallInteger('failures_last_30_days')->nullable();
            $table->timestampTz('first_seen_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();
            $table->jsonb('context')->nullable();
            $table->timestampsTz();

            $table->index(['check_key', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['fingerprint', 'created_at']);
            $table->index(['workflow_subject_type', 'workflow_subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_health_check_results');
        Schema::dropIfExists('operational_health_check_runs');
    }
};
