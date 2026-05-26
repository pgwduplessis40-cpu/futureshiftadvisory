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
        Schema::create('npo_compliance_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->uuid('npo_engagement_id')->nullable();
            $table->string('type', 120);
            $table->string('severity', 40);
            $table->text('message');
            $table->string('source', 80)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('triggered_at');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'severity']);
            $table->index(['npo_engagement_id', 'type']);
            $table->index('acknowledged_at');
            $table->unique(['client_id', 'npo_engagement_id', 'type'], 'npo_compliance_alerts_unique_scope');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE npo_compliance_alerts
                ADD CONSTRAINT npo_compliance_alerts_engagement_client_fk
                FOREIGN KEY (npo_engagement_id, client_id)
                REFERENCES npo_engagements (id, client_id)
                ON DELETE CASCADE
            SQL);
        }

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('npo_compliance_alerts');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE npo_compliance_alerts ENABLE ROW LEVEL SECURITY;
            ALTER TABLE npo_compliance_alerts FORCE ROW LEVEL SECURITY;

            CREATE POLICY npo_compliance_alerts_client_scope ON npo_compliance_alerts
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
