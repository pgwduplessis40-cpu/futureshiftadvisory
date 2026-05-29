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
        Schema::create('report_section_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignUuid('report_section_id')->constrained('report_sections')->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('entrepreneur_profile_id')->nullable()->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('title_before');
            $table->string('title_after');
            $table->text('body_before');
            $table->text('body_after');
            $table->jsonb('metadata_before')->nullable();
            $table->jsonb('metadata_after')->nullable();
            $table->foreignId('edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('edited_at');
            $table->timestampsTz();

            $table->unique(['report_section_id', 'revision_number'], 'report_section_revisions_number_unique');
            $table->index(['report_id', 'edited_at']);
            $table->index(['client_id', 'edited_at']);
            $table->index(['entrepreneur_profile_id', 'edited_at'], 'report_section_revisions_entrepreneur_idx');
        });

        Schema::create('report_section_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignUuid('report_section_id')->constrained('report_sections')->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('entrepreneur_profile_id')->nullable()->constrained('entrepreneur_profiles')->cascadeOnDelete();
            $table->string('visibility', 40)->default('advisor_only');
            $table->text('body');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['report_id', 'created_at']);
            $table->index(['report_section_id', 'resolved_at']);
            $table->index(['client_id', 'created_at']);
            $table->index(['entrepreneur_profile_id', 'created_at'], 'report_section_comments_entrepreneur_idx');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('report_section_comments');
        Schema::dropIfExists('report_section_revisions');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['report_section_revisions', 'report_section_comments'] as $table) {
            DB::unprepared(<<<SQL
                ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;
                ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;

                CREATE POLICY {$table}_client_scope ON {$table}
                    USING (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR client_id::text = ANY (fsa_current_client_ids())
                        OR EXISTS (
                            SELECT 1
                            FROM entrepreneur_profiles
                            WHERE entrepreneur_profiles.id = {$table}.entrepreneur_profile_id
                            AND (
                                entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                                OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                            )
                        )
                    )
                    WITH CHECK (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR client_id::text = ANY (fsa_current_client_ids())
                        OR EXISTS (
                            SELECT 1
                            FROM entrepreneur_profiles
                            WHERE entrepreneur_profiles.id = {$table}.entrepreneur_profile_id
                            AND (
                                entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                                OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                            )
                        )
                    );
            SQL);
        }
    }
};
