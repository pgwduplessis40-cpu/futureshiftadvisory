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
        Schema::create('payment_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->foreignUuid('payment_authority_id')->constrained('payment_authorities')->cascadeOnDelete();
            $table->string('cadence', 40);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NZD');
            $table->timestampTz('next_run_at');
            $table->string('status', 40)->default('active');
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'status', 'next_run_at']);
            $table->index(['proposal_id', 'status']);
            $table->index(['payment_authority_id', 'status']);
            $table->index('created_by_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE payment_schedules ENABLE ROW LEVEL SECURITY;
            ALTER TABLE payment_schedules FORCE ROW LEVEL SECURITY;

            CREATE POLICY payment_schedules_client_scope ON payment_schedules
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
