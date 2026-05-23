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
        Schema::create('coach_referral_suggestions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('coaching_signal_id')->unique()->constrained('coaching_signals')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('suggested_specialisation', 80);
            $table->string('threshold_ref', 120);
            $table->text('rationale');
            $table->jsonb('evidence')->nullable();
            $table->string('status', 40)->default('suggested');
            $table->timestampTz('surfaced_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index(['suggested_specialisation', 'status']);
            $table->index('reviewed_by_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_referral_suggestions');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE coach_referral_suggestions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE coach_referral_suggestions FORCE ROW LEVEL SECURITY;

            CREATE POLICY coach_referral_suggestions_scope ON coach_referral_suggestions
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM client_team
                        WHERE client_team.client_id = coach_referral_suggestions.client_id
                        AND client_team.user_id::text = fsa_current_user_id()
                        AND client_team.role IN ('lead_advisor', 'advisor')
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                );
        SQL);
    }
};
