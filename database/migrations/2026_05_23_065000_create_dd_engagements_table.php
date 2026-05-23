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
        Schema::create('dd_engagements', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('target_name');
            $table->jsonb('target_details');
            $table->string('status', 40)->default('in_progress');
            $table->string('recommendation', 40)->nullable();
            $table->foreignUuid('conflict_declaration_id')->constrained('conflict_declarations')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('disclaimer_acknowledged_at');
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index('conflict_declaration_id');
            $table->index('created_by_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('dd_engagements');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE dd_engagements ENABLE ROW LEVEL SECURITY;
            ALTER TABLE dd_engagements FORCE ROW LEVEL SECURITY;

            CREATE POLICY dd_engagements_scope ON dd_engagements
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
