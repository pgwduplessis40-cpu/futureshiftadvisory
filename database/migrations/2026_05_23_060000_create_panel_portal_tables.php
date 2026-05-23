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
        Schema::create('panel_members', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('invite_token_id')->nullable()->constrained('invite_tokens')->nullOnDelete();
            $table->string('panel_type', 40);
            $table->string('status', 40)->default('invited');
            $table->jsonb('application')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('applied_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampsTz();

            $table->index(['panel_type', 'status']);
            $table->index('user_id');
            $table->index('invite_token_id');
            $table->index('approved_by_user_id');
        });

        Schema::create('panel_agreements', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('panel_member_id')->constrained('panel_members')->cascadeOnDelete();
            $table->string('status', 40)->default('pending_signature');
            $table->jsonb('terms');
            $table->string('pdf_path')->nullable();
            $table->text('pdf_sha256_envelope')->nullable();
            $table->jsonb('pdf_envelope_meta')->nullable();
            $table->unsignedInteger('pdf_byte_size')->nullable();
            $table->foreignId('signed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('generated_at');
            $table->timestampTz('signed_at')->nullable();
            $table->timestampsTz();

            $table->index(['panel_member_id', 'status']);
            $table->index('signed_by_user_id');
        });

        Schema::create('referrals', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('panel_member_id')->constrained('panel_members')->cascadeOnDelete();
            $table->string('panel_type', 40);
            $table->string('referral_type', 80);
            $table->string('stage', 40)->default('draft');
            $table->jsonb('payload')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'panel_type', 'stage']);
            $table->index(['panel_member_id', 'stage']);
            $table->index('created_by_user_id');
        });

        Schema::create('referral_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestampTz('sent_at');
            $table->timestampsTz();

            $table->index(['referral_id', 'sent_at']);
            $table->index(['client_id', 'sent_at']);
            $table->index('sender_user_id');
        });

        Schema::create('reverse_referrals', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('panel_member_id')->constrained('panel_members')->cascadeOnDelete();
            $table->string('target_type', 40);
            $table->string('name');
            $table->string('email');
            $table->string('company')->nullable();
            $table->jsonb('payload')->nullable();
            $table->foreignId('prospect_lead_id')->nullable()->constrained('prospect_leads')->nullOnDelete();
            $table->foreignUuid('entrepreneur_profile_id')->nullable()->constrained('entrepreneur_profiles')->nullOnDelete();
            $table->timestampTz('submitted_at');
            $table->timestampsTz();

            $table->index(['panel_member_id', 'submitted_at']);
            $table->index('prospect_lead_id');
            $table->index('entrepreneur_profile_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('reverse_referrals');
        Schema::dropIfExists('referral_messages');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('panel_agreements');
        Schema::dropIfExists('panel_members');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE panel_members ENABLE ROW LEVEL SECURITY;
            ALTER TABLE panel_members FORCE ROW LEVEL SECURITY;

            CREATE POLICY panel_members_scope ON panel_members
                USING (
                    fsa_current_role() IN ('super_admin', 'system', 'advisor')
                    OR user_id::text = fsa_current_user_id()
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system', 'advisor')
                    OR user_id::text = fsa_current_user_id()
                );

            ALTER TABLE panel_agreements ENABLE ROW LEVEL SECURITY;
            ALTER TABLE panel_agreements FORCE ROW LEVEL SECURITY;

            CREATE POLICY panel_agreements_scope ON panel_agreements
                USING (
                    fsa_current_role() IN ('super_admin', 'system', 'advisor')
                    OR EXISTS (
                        SELECT 1 FROM panel_members
                        WHERE panel_members.id = panel_agreements.panel_member_id
                        AND panel_members.user_id::text = fsa_current_user_id()
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system', 'advisor')
                    OR EXISTS (
                        SELECT 1 FROM panel_members
                        WHERE panel_members.id = panel_agreements.panel_member_id
                        AND panel_members.user_id::text = fsa_current_user_id()
                    )
                );

            ALTER TABLE referrals ENABLE ROW LEVEL SECURITY;
            ALTER TABLE referrals FORCE ROW LEVEL SECURITY;

            CREATE POLICY referrals_scope ON referrals
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1 FROM panel_members
                        WHERE panel_members.id = referrals.panel_member_id
                        AND panel_members.user_id::text = fsa_current_user_id()
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1 FROM panel_members
                        WHERE panel_members.id = referrals.panel_member_id
                        AND panel_members.user_id::text = fsa_current_user_id()
                    )
                );

            ALTER TABLE referral_messages ENABLE ROW LEVEL SECURITY;
            ALTER TABLE referral_messages FORCE ROW LEVEL SECURITY;

            CREATE POLICY referral_messages_scope ON referral_messages
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM referrals
                        JOIN panel_members ON panel_members.id = referrals.panel_member_id
                        WHERE referrals.id = referral_messages.referral_id
                        AND panel_members.user_id::text = fsa_current_user_id()
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM referrals
                        JOIN panel_members ON panel_members.id = referrals.panel_member_id
                        WHERE referrals.id = referral_messages.referral_id
                        AND panel_members.user_id::text = fsa_current_user_id()
                    )
                );

            ALTER TABLE reverse_referrals ENABLE ROW LEVEL SECURITY;
            ALTER TABLE reverse_referrals FORCE ROW LEVEL SECURITY;

            CREATE POLICY reverse_referrals_scope ON reverse_referrals
                USING (
                    fsa_current_role() IN ('super_admin', 'system', 'advisor')
                    OR EXISTS (
                        SELECT 1 FROM panel_members
                        WHERE panel_members.id = reverse_referrals.panel_member_id
                        AND panel_members.user_id::text = fsa_current_user_id()
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system', 'advisor')
                    OR EXISTS (
                        SELECT 1 FROM panel_members
                        WHERE panel_members.id = reverse_referrals.panel_member_id
                        AND panel_members.user_id::text = fsa_current_user_id()
                    )
                );
        SQL);
    }
};
