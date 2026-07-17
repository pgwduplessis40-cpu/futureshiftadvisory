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
        Schema::create('advisor_client_transfer_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_advisor_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->string('status', 32)->default('pending');
            $table->text('decision_reason')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
            $table->index(['requested_by_user_id', 'status']);
            $table->index(['client_id', 'status']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE advisor_client_transfer_requests ENABLE ROW LEVEL SECURITY;
            ALTER TABLE advisor_client_transfer_requests FORCE ROW LEVEL SECURITY;

            CREATE POLICY advisor_client_transfer_requests_select_scope ON advisor_client_transfer_requests
                FOR SELECT
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR requested_by_user_id::text = fsa_current_user_id()
                );

            CREATE POLICY advisor_client_transfer_requests_insert_scope ON advisor_client_transfer_requests
                FOR INSERT
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        requested_by_user_id::text = fsa_current_user_id()
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                );

            CREATE POLICY advisor_client_transfer_requests_update_scope ON advisor_client_transfer_requests
                FOR UPDATE
                USING (fsa_current_role() IN ('super_admin', 'system'))
                WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_client_transfer_requests');
    }
};
