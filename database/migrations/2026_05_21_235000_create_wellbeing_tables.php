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
        Schema::create('wellbeing_checkins', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_start');
            $table->unsignedTinyInteger('business_confidence');
            $table->unsignedTinyInteger('personal_coping');
            $table->text('notes')->nullable();
            $table->timestampTz('submitted_at');
            $table->timestampsTz();

            $table->unique(['client_id', 'user_id', 'period_start']);
            $table->index(['client_id', 'period_start']);
            $table->index(['user_id', 'period_start']);
        });

        Schema::create('coaching_signals', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('trigger_checkin_id')->nullable()->constrained('wellbeing_checkins')->nullOnDelete();
            $table->string('signal_type', 80);
            $table->string('severity', 40)->default('advisor_attention');
            $table->string('status', 40)->default('detected');
            $table->jsonb('evidence')->nullable();
            $table->timestampTz('generated_at');
            $table->timestampsTz();

            $table->unique(['client_id', 'signal_type', 'trigger_checkin_id']);
            $table->index(['client_id', 'status']);
            $table->index('generated_at');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_signals');
        Schema::dropIfExists('wellbeing_checkins');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE wellbeing_checkins ENABLE ROW LEVEL SECURITY;
            ALTER TABLE wellbeing_checkins FORCE ROW LEVEL SECURITY;

            CREATE POLICY wellbeing_checkins_scope ON wellbeing_checkins
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR user_id::text = fsa_current_user_id()
                    OR EXISTS (
                        SELECT 1
                        FROM client_team
                        WHERE client_team.client_id = wellbeing_checkins.client_id
                        AND client_team.user_id::text = fsa_current_user_id()
                        AND client_team.role = 'lead_advisor'
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        user_id::text = fsa_current_user_id()
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                );

            ALTER TABLE coaching_signals ENABLE ROW LEVEL SECURITY;
            ALTER TABLE coaching_signals FORCE ROW LEVEL SECURITY;

            CREATE POLICY coaching_signals_scope ON coaching_signals
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM client_team
                        WHERE client_team.client_id = coaching_signals.client_id
                        AND client_team.user_id::text = fsa_current_user_id()
                        AND client_team.role = 'lead_advisor'
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                );
        SQL);
    }
};
