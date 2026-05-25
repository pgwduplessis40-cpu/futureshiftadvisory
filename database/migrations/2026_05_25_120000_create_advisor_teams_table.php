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
        Schema::create('advisor_teams', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->foreignId('lead_advisor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('active');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index('lead_advisor_user_id');
            $table->index('status');
        });

        Schema::create('advisor_team_members', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('advisor_team_id')->constrained('advisor_teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->default('member');
            $table->timestampTz('joined_at');
            $table->timestampTz('left_at')->nullable();
            $table->timestampsTz();

            $table->unique(['advisor_team_id', 'user_id']);
            $table->index(['user_id', 'left_at']);
            $table->index('role');
        });

        Schema::table('client_team', function (Blueprint $table): void {
            $table->foreignUuid('advisor_team_id')->nullable()->after('user_id')->constrained('advisor_teams')->nullOnDelete();
            $table->index(['advisor_team_id', 'client_id']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::unprepared(<<<'SQL'
                DROP POLICY IF EXISTS client_team_scope_select_update_delete ON client_team;
                DROP POLICY IF EXISTS advisor_team_members_scope ON advisor_team_members;
                DROP POLICY IF EXISTS advisor_teams_scope ON advisor_teams;

                CREATE POLICY client_team_scope_select_update_delete ON client_team
                    USING (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR user_id::text = fsa_current_user_id()
                        OR client_id::text = ANY (fsa_current_client_ids())
                    )
                    WITH CHECK (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR user_id::text = fsa_current_user_id()
                        OR client_id::text = ANY (fsa_current_client_ids())
                    );
            SQL);
        }

        Schema::table('client_team', function (Blueprint $table): void {
            $table->dropForeign(['advisor_team_id']);
            $table->dropIndex(['advisor_team_id', 'client_id']);
            $table->dropColumn('advisor_team_id');
        });

        Schema::dropIfExists('advisor_team_members');
        Schema::dropIfExists('advisor_teams');
    }

    private function installRlsPolicies(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE advisor_teams ENABLE ROW LEVEL SECURITY;
            ALTER TABLE advisor_teams FORCE ROW LEVEL SECURITY;

            CREATE POLICY advisor_teams_scope ON advisor_teams
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR lead_advisor_user_id::text = fsa_current_user_id()
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR lead_advisor_user_id::text = fsa_current_user_id()
                );

            ALTER TABLE advisor_team_members ENABLE ROW LEVEL SECURITY;
            ALTER TABLE advisor_team_members FORCE ROW LEVEL SECURITY;

            CREATE POLICY advisor_team_members_scope ON advisor_team_members
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR user_id::text = fsa_current_user_id()
                    OR EXISTS (
                        SELECT 1
                        FROM advisor_teams
                        WHERE advisor_teams.id = advisor_team_members.advisor_team_id
                        AND advisor_teams.lead_advisor_user_id::text = fsa_current_user_id()
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM advisor_teams
                        WHERE advisor_teams.id = advisor_team_members.advisor_team_id
                        AND advisor_teams.lead_advisor_user_id::text = fsa_current_user_id()
                    )
                );

            DROP POLICY IF EXISTS client_team_scope_select_update_delete ON client_team;

            CREATE POLICY client_team_scope_select_update_delete ON client_team
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR user_id::text = fsa_current_user_id()
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM advisor_teams
                        WHERE advisor_teams.id = client_team.advisor_team_id
                        AND advisor_teams.lead_advisor_user_id::text = fsa_current_user_id()
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM advisor_team_members
                        WHERE advisor_team_members.advisor_team_id = client_team.advisor_team_id
                        AND advisor_team_members.user_id::text = fsa_current_user_id()
                        AND advisor_team_members.role = 'lead'
                        AND advisor_team_members.left_at IS NULL
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR user_id::text = fsa_current_user_id()
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM advisor_teams
                        WHERE advisor_teams.id = client_team.advisor_team_id
                        AND advisor_teams.lead_advisor_user_id::text = fsa_current_user_id()
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM advisor_team_members
                        WHERE advisor_team_members.advisor_team_id = client_team.advisor_team_id
                        AND advisor_team_members.user_id::text = fsa_current_user_id()
                        AND advisor_team_members.role = 'lead'
                        AND advisor_team_members.left_at IS NULL
                    )
                );
        SQL);
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
