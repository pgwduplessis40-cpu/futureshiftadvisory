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
        Schema::create('proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('fee_calculation_id')->constrained('fee_calculations')->cascadeOnDelete();
            $table->string('status', 48);
            $table->unsignedInteger('version')->default(1);
            $table->jsonb('scope');
            $table->jsonb('services');
            $table->jsonb('pv_summary');
            $table->decimal('roi_ratio', 12, 4)->default(0);
            $table->jsonb('acceptance_terms');
            $table->string('pdf_path')->nullable();
            $table->unsignedInteger('pdf_byte_size')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('recalled_at')->nullable();
            $table->foreignId('recalled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('expired_at')->nullable();
            $table->uuid('renewed_from_proposal_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'status', 'expires_at']);
            $table->index('fee_calculation_id');
            $table->index('renewed_from_proposal_id');
        });

        Schema::table('proposals', function (Blueprint $table): void {
            $table->foreign('renewed_from_proposal_id')
                ->references('id')
                ->on('proposals')
                ->nullOnDelete();
        });

        Schema::create('consents', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('election', 40);
            $table->jsonb('evidence')->nullable();
            $table->foreignId('captured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('captured_at')->nullable();
            $table->timestampsTz();

            $table->unique(['proposal_id', 'type']);
            $table->index(['client_id', 'type']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
        Schema::dropIfExists('proposals');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE proposals ENABLE ROW LEVEL SECURITY;
            ALTER TABLE proposals FORCE ROW LEVEL SECURITY;

            CREATE POLICY proposals_client_scope ON proposals
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE consents ENABLE ROW LEVEL SECURITY;
            ALTER TABLE consents FORCE ROW LEVEL SECURITY;

            CREATE POLICY consents_client_scope ON consents
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
