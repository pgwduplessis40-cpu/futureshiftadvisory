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
        Schema::create('security_audits', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('period', 32)->unique();
            $table->string('auditor')->nullable();
            $table->jsonb('scope');
            $table->string('status', 32)->default('planned');
            $table->jsonb('evidence_manifest')->nullable();
            $table->jsonb('findings')->nullable();
            $table->string('report_path')->nullable();
            $table->timestampTz('prepared_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'period']);
            $table->index('prepared_at');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE security_audits ENABLE ROW LEVEL SECURITY;
                ALTER TABLE security_audits FORCE ROW LEVEL SECURITY;

                CREATE POLICY security_audits_admin_scope ON security_audits
                    USING (fsa_current_role() IN ('super_admin', 'system'))
                    WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audits');
    }
};
