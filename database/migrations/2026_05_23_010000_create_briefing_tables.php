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
        Schema::create('meetings', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('title');
            $table->timestampTz('scheduled_at');
            $table->string('location')->nullable();
            $table->string('link')->nullable();
            $table->jsonb('attendees')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_ref')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'scheduled_at']);
            $table->index('created_by_user_id');
            $table->index('external_ref');
        });

        Schema::create('industry_briefings', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->date('period');
            $table->text('body');
            $table->jsonb('sources');
            $table->string('status', 40)->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->unique(['client_id', 'period']);
            $table->index(['client_id', 'status']);
            $table->index('reviewed_by_user_id');
        });

        Schema::create('pre_meeting_briefs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('meeting_id')->unique()->constrained('meetings')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->timestampTz('meeting_at');
            $table->text('body');
            $table->jsonb('red_flag_ids');
            $table->timestampTz('generated_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'meeting_at']);
            $table->index('reviewed_by_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_meeting_briefs');
        Schema::dropIfExists('industry_briefings');
        Schema::dropIfExists('meetings');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE meetings ENABLE ROW LEVEL SECURITY;
            ALTER TABLE meetings FORCE ROW LEVEL SECURITY;

            CREATE POLICY meetings_client_scope ON meetings
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE industry_briefings ENABLE ROW LEVEL SECURITY;
            ALTER TABLE industry_briefings FORCE ROW LEVEL SECURITY;

            CREATE POLICY industry_briefings_client_scope ON industry_briefings
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE pre_meeting_briefs ENABLE ROW LEVEL SECURITY;
            ALTER TABLE pre_meeting_briefs FORCE ROW LEVEL SECURITY;

            CREATE POLICY pre_meeting_briefs_client_scope ON pre_meeting_briefs
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
