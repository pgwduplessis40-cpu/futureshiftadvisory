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
        Schema::create('reports', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('title');
            $table->string('pdf_path')->nullable();
            $table->unsignedInteger('pdf_byte_size')->nullable();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('generated_at');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'type', 'generated_at']);
            $table->index('generated_by_user_id');
        });

        Schema::create('report_sections', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('key', 120);
            $table->string('title');
            $table->text('body');
            $table->unsignedSmallInteger('position');
            $table->string('lens', 40)->nullable();
            $table->jsonb('attributions');
            $table->string('document_support', 40);
            $table->string('document_support_note');
            $table->text('data_quality_note');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['report_id', 'position']);
            $table->index(['client_id', 'lens']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('report_sections');
        Schema::dropIfExists('reports');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE reports ENABLE ROW LEVEL SECURITY;
            ALTER TABLE reports FORCE ROW LEVEL SECURITY;

            CREATE POLICY reports_client_scope ON reports
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE report_sections ENABLE ROW LEVEL SECURITY;
            ALTER TABLE report_sections FORCE ROW LEVEL SECURITY;

            CREATE POLICY report_sections_client_scope ON report_sections
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );
        SQL);
    }
};
