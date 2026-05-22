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
        Schema::table('proposals', function (Blueprint $table): void {
            $table->timestampTz('awaiting_signature_at')->nullable()->after('expired_at');
            $table->timestampTz('signed_at')->nullable()->after('awaiting_signature_at');
            $table->foreignId('signed_by_user_id')->nullable()->after('signed_at')->constrained('users')->nullOnDelete();
            $table->string('signature_evidence_path')->nullable()->after('signed_by_user_id');
            $table->text('signature_evidence_sha256_envelope')->nullable()->after('signature_evidence_path');
            $table->jsonb('signature_envelope_meta')->nullable()->after('signature_evidence_sha256_envelope');
            $table->unsignedInteger('signature_evidence_byte_size')->nullable()->after('signature_envelope_meta');

            $table->index(['client_id', 'awaiting_signature_at']);
            $table->index(['client_id', 'signed_at']);
            $table->index('signed_by_user_id');
        });

        Schema::create('proposal_signoff_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('step', 40);
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('completed_at');
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();

            $table->unique(['proposal_id', 'step']);
            $table->index(['client_id', 'step']);
            $table->index('completed_by_user_id');
        });

        Schema::create('payment_authorities', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('gateway', 40);
            $table->string('gateway_customer_ref')->nullable();
            $table->text('gateway_token_envelope');
            $table->string('status', 40)->default('active');
            $table->foreignId('authorised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('authorised_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index(['proposal_id', 'status']);
            $table->index(['gateway', 'status']);
            $table->index('authorised_by_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_authorities');
        Schema::dropIfExists('proposal_signoff_steps');

        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropForeign(['signed_by_user_id']);
            $table->dropIndex(['client_id', 'awaiting_signature_at']);
            $table->dropIndex(['client_id', 'signed_at']);
            $table->dropIndex(['signed_by_user_id']);
            $table->dropColumn([
                'awaiting_signature_at',
                'signed_at',
                'signed_by_user_id',
                'signature_evidence_path',
                'signature_evidence_sha256_envelope',
                'signature_envelope_meta',
                'signature_evidence_byte_size',
            ]);
        });
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['proposal_signoff_steps', 'payment_authorities'] as $table) {
            DB::unprepared(<<<SQL
                ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;
                ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;

                CREATE POLICY {$table}_client_scope ON {$table}
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
    }
};
