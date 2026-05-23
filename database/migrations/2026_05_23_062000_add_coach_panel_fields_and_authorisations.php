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
        Schema::create('coach_referral_authorisations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('authorised_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('staff_name');
            $table->string('staff_email')->nullable();
            $table->string('purpose')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampTz('granted_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'revoked_at']);
            $table->index('authorised_by_user_id');
        });

        Schema::table('panel_members', function (Blueprint $table): void {
            $table->jsonb('coach_specialisations')->nullable()->after('fsp_last_checked_at');
            $table->jsonb('coach_profile')->nullable()->after('coach_specialisations');
            $table->jsonb('professional_memberships')->nullable()->after('coach_profile');
            $table->jsonb('coach_vetting')->nullable()->after('professional_memberships');
            $table->foreignId('coach_vetted_by_user_id')->nullable()->after('coach_vetting')->constrained('users')->nullOnDelete();
            $table->timestampTz('coach_vetted_at')->nullable()->after('coach_vetted_by_user_id');

            $table->index('coach_vetted_by_user_id');
        });

        Schema::table('referrals', function (Blueprint $table): void {
            $table->foreignUuid('entrepreneur_profile_id')->nullable()->after('client_id')->constrained('entrepreneur_profiles')->nullOnDelete();
            $table->string('coach_specialisation', 80)->nullable()->after('referral_type');
            $table->string('referred_subject_type', 40)->nullable()->after('coach_specialisation');
            $table->foreignUuid('coach_referral_authorisation_id')->nullable()->after('referred_subject_type')->constrained('coach_referral_authorisations')->nullOnDelete();

            $table->index('entrepreneur_profile_id');
            $table->index(['coach_specialisation', 'stage']);
            $table->index('coach_referral_authorisation_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE referrals ALTER COLUMN client_id DROP NOT NULL');
            DB::statement('ALTER TABLE referral_messages ALTER COLUMN client_id DROP NOT NULL');
        }

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table): void {
            $table->dropIndex(['entrepreneur_profile_id']);
            $table->dropIndex(['coach_specialisation', 'stage']);
            $table->dropIndex(['coach_referral_authorisation_id']);
            $table->dropConstrainedForeignId('entrepreneur_profile_id');
            $table->dropConstrainedForeignId('coach_referral_authorisation_id');
            $table->dropColumn(['coach_specialisation', 'referred_subject_type']);
        });

        Schema::table('panel_members', function (Blueprint $table): void {
            $table->dropIndex(['coach_vetted_by_user_id']);
            $table->dropConstrainedForeignId('coach_vetted_by_user_id');
            $table->dropColumn([
                'coach_specialisations',
                'coach_profile',
                'professional_memberships',
                'coach_vetting',
                'coach_vetted_at',
            ]);
        });

        Schema::dropIfExists('coach_referral_authorisations');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE referrals ALTER COLUMN client_id SET NOT NULL');
            DB::statement('ALTER TABLE referral_messages ALTER COLUMN client_id SET NOT NULL');
        }
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE coach_referral_authorisations ENABLE ROW LEVEL SECURITY;
            ALTER TABLE coach_referral_authorisations FORCE ROW LEVEL SECURITY;

            CREATE POLICY coach_referral_authorisations_scope ON coach_referral_authorisations
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
