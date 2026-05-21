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
        Schema::create('offboarding_records', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('completed');
            $table->timestampTz('triggered_at');
            $table->string('final_report_path');
            $table->string('engagement_summary_path');
            $table->string('handover_path');
            $table->string('exit_interview_path');
            $table->string('privacy_notice_path');
            $table->timestampTz('reengagement_due')->nullable();
            $table->timestampTz('reengagement_reminder_sent_at')->nullable();
            $table->boolean('advisor_capacity_released')->default(false);
            $table->integer('advisor_capacity_before')->nullable();
            $table->integer('advisor_capacity_after')->nullable();
            $table->integer('advisor_capacity_delta')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'triggered_at']);
            $table->index(['status', 'reengagement_due']);
            $table->index('reengagement_reminder_sent_at');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::unprepared(<<<'SQL'
                DROP POLICY IF EXISTS offboarding_records_scope ON offboarding_records;
                ALTER TABLE offboarding_records DISABLE ROW LEVEL SECURITY;
            SQL);
        }

        Schema::dropIfExists('offboarding_records');
    }

    private function installRlsPolicies(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE offboarding_records ENABLE ROW LEVEL SECURITY;
            ALTER TABLE offboarding_records FORCE ROW LEVEL SECURITY;

            CREATE POLICY offboarding_records_scope ON offboarding_records
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

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
