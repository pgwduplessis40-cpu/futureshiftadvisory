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
        Schema::create('knowledge_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedTinyInteger('financial_literacy');
            $table->unsignedTinyInteger('strategic_awareness');
            $table->unsignedTinyInteger('leadership');
            $table->jsonb('calibration');
            $table->timestampTz('assessed_at');
            $table->foreignId('assessed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'assessed_at']);
            $table->index('assessed_by_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_assessments');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE knowledge_assessments ENABLE ROW LEVEL SECURITY;
            ALTER TABLE knowledge_assessments FORCE ROW LEVEL SECURITY;

            CREATE POLICY knowledge_assessments_client_scope ON knowledge_assessments
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
