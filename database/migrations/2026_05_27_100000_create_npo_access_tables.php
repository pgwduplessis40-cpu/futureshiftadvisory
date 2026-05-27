<?php

declare(strict_types=1);

use App\Enums\ReportType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npo_board_members', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->uuid('npo_engagement_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('treasurer')->default(false);
            $table->boolean('active')->default(true);
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['npo_engagement_id', 'user_id'], 'npo_board_members_engagement_user_unique');
            $table->index(['client_id', 'active']);
            $table->index(['user_id', 'active']);
            $table->index('revoked_at');
        });

        Schema::create('npo_funder_report_links', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->uuid('npo_engagement_id');
            $table->foreignUuid('report_id')->nullable()->constrained('reports')->cascadeOnDelete();
            $table->foreignUuid('client_funder_record_id')->nullable()->constrained('client_funder_records')->nullOnDelete();
            $table->string('guest_email');
            $table->string('status', 40)->default('requested');
            $table->char('token_hash', 64)->nullable()->unique();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('declined_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index(['npo_engagement_id', 'status']);
            $table->index(['report_id', 'status']);
            $table->index(['expires_at', 'revoked_at']);
        });

        Schema::create('npo_funder_report_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('npo_funder_report_link_id')->constrained('npo_funder_report_links')->cascadeOnDelete();
            $table->foreignUuid('report_id')->constrained('reports')->cascadeOnDelete();
            $table->timestampTz('accessed_at');
            $table->jsonb('metadata')->nullable();

            $table->index(['client_id', 'accessed_at']);
            $table->index(['report_id', 'accessed_at']);
        });

        $this->extendRlsContext();

        if ($this->onPostgres()) {
            DB::statement(<<<'SQL'
                ALTER TABLE npo_board_members
                ADD CONSTRAINT npo_board_members_engagement_client_fk
                FOREIGN KEY (npo_engagement_id, client_id)
                REFERENCES npo_engagements (id, client_id)
                ON DELETE CASCADE
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE npo_funder_report_links
                ADD CONSTRAINT npo_funder_report_links_engagement_client_fk
                FOREIGN KEY (npo_engagement_id, client_id)
                REFERENCES npo_engagements (id, client_id)
                ON DELETE CASCADE
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE npo_funder_report_links
                ADD CONSTRAINT npo_funder_report_links_status_check
                CHECK (status IN ('requested', 'approved', 'declined', 'revoked'))
            SQL);
        }

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('npo_funder_report_sessions');
        Schema::dropIfExists('npo_funder_report_links');
        Schema::dropIfExists('npo_board_members');

        if (! $this->onPostgres()) {
            return;
        }

        $this->restoreLegacyPolicies();

        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS fsa_user_is_board_member_of(uuid);
            DROP FUNCTION IF EXISTS fsa_current_npo_engagement_id();
            DROP FUNCTION IF EXISTS fsa_current_report_id();
            DROP FUNCTION IF EXISTS fsa_set_request_context(text, text, text, text, text);

            CREATE OR REPLACE FUNCTION fsa_set_request_context(
                p_role text,
                p_client_ids text,
                p_user_id text DEFAULT NULL
            )
            RETURNS void
            LANGUAGE plpgsql
            AS $$
            BEGIN
                PERFORM set_config('fsa.role', COALESCE(p_role, ''), false);
                PERFORM set_config('fsa.client_ids', COALESCE(p_client_ids, ''), false);
                PERFORM set_config('fsa.user_id', COALESCE(p_user_id, ''), false);
            END;
            $$;
        SQL);
    }

    private function extendRlsContext(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS fsa_set_request_context(text, text, text);

            CREATE OR REPLACE FUNCTION fsa_set_request_context(
                p_role text,
                p_client_ids text,
                p_user_id text DEFAULT NULL,
                p_report_id text DEFAULT NULL,
                p_npo_engagement_id text DEFAULT NULL
            )
            RETURNS void
            LANGUAGE plpgsql
            AS $$
            BEGIN
                PERFORM set_config('fsa.role', COALESCE(p_role, ''), false);
                PERFORM set_config('fsa.client_ids', COALESCE(p_client_ids, ''), false);
                PERFORM set_config('fsa.user_id', COALESCE(p_user_id, ''), false);
                PERFORM set_config('fsa.report_id', COALESCE(p_report_id, ''), false);
                PERFORM set_config('fsa.npo_engagement_id', COALESCE(p_npo_engagement_id, ''), false);
            END;
            $$;

            CREATE OR REPLACE FUNCTION fsa_current_report_id()
            RETURNS text
            LANGUAGE sql
            STABLE
            AS $$
                SELECT NULLIF(current_setting('fsa.report_id', true), '');
            $$;

            CREATE OR REPLACE FUNCTION fsa_current_npo_engagement_id()
            RETURNS text
            LANGUAGE sql
            STABLE
            AS $$
                SELECT NULLIF(current_setting('fsa.npo_engagement_id', true), '');
            $$;

            CREATE OR REPLACE FUNCTION fsa_user_is_board_member_of(p_npo_engagement_id uuid)
            RETURNS boolean
            LANGUAGE sql
            STABLE
            SECURITY DEFINER
            SET search_path = pg_catalog, public
            AS $$
                SELECT EXISTS (
                    SELECT 1
                    FROM public.npo_board_members
                    WHERE npo_engagement_id = p_npo_engagement_id
                        AND user_id::text = public.fsa_current_user_id()
                        AND active = true
                        AND revoked_at IS NULL
                );
            $$;

            GRANT EXECUTE ON FUNCTION fsa_user_is_board_member_of(uuid) TO CURRENT_USER;
            GRANT EXECUTE ON FUNCTION fsa_user_is_board_member_of(uuid) TO PUBLIC;
        SQL);
    }

    private function installRlsPolicies(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        $funderReport = ReportType::FunderAccountability->value;

        DB::unprepared(<<<SQL
            ALTER TABLE npo_board_members ENABLE ROW LEVEL SECURITY;
            ALTER TABLE npo_board_members FORCE ROW LEVEL SECURITY;

            CREATE POLICY npo_board_members_read ON npo_board_members
                FOR SELECT
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR user_id::text = fsa_current_user_id()
                );

            CREATE POLICY npo_board_members_manage ON npo_board_members
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE npo_funder_report_links ENABLE ROW LEVEL SECURITY;
            ALTER TABLE npo_funder_report_links FORCE ROW LEVEL SECURITY;

            CREATE POLICY npo_funder_report_links_client_scope ON npo_funder_report_links
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE npo_funder_report_sessions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE npo_funder_report_sessions FORCE ROW LEVEL SECURITY;

            CREATE POLICY npo_funder_report_sessions_client_scope ON npo_funder_report_sessions
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            DROP POLICY IF EXISTS npo_engagements_client_scope ON npo_engagements;
            CREATE POLICY npo_engagements_client_scope ON npo_engagements
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR (
                        fsa_current_role() = 'npo_board_member'
                        AND id::text = fsa_current_npo_engagement_id()
                        AND fsa_user_is_board_member_of(id)
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            DROP POLICY IF EXISTS npo_dimension_scores_client_scope ON npo_dimension_scores;
            CREATE POLICY npo_dimension_scores_client_scope ON npo_dimension_scores
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR (
                        fsa_current_role() = 'npo_board_member'
                        AND npo_engagement_id::text = fsa_current_npo_engagement_id()
                        AND fsa_user_is_board_member_of(npo_engagement_id)
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            DROP POLICY IF EXISTS milestones_client_scope ON milestones;
            CREATE POLICY milestones_client_scope ON milestones
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR (
                        fsa_current_role() = 'npo_board_member'
                        AND npo_engagement_id::text = fsa_current_npo_engagement_id()
                        AND fsa_user_is_board_member_of(npo_engagement_id)
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            DROP POLICY IF EXISTS milestone_actions_client_scope ON milestone_actions;
            CREATE POLICY milestone_actions_client_scope ON milestone_actions
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR (
                        fsa_current_role() = 'npo_board_member'
                        AND npo_engagement_id::text = fsa_current_npo_engagement_id()
                        AND fsa_user_is_board_member_of(npo_engagement_id)
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            DROP POLICY IF EXISTS reports_client_scope ON reports;
            CREATE POLICY reports_client_scope ON reports
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = reports.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                    OR (
                        fsa_current_role() = 'npo_board_member'
                        AND npo_engagement_id::text = fsa_current_npo_engagement_id()
                        AND fsa_user_is_board_member_of(npo_engagement_id)
                    )
                    OR (
                        fsa_current_role() = 'funder_contact'
                        AND id::text = fsa_current_report_id()
                        AND type = '{$funderReport}'
                        AND review_status = 'reviewed'
                        AND reviewed_at IS NOT NULL
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = reports.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            DROP POLICY IF EXISTS report_sections_client_scope ON report_sections;
            CREATE POLICY report_sections_client_scope ON report_sections
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = report_sections.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                    OR (
                        fsa_current_role() = 'npo_board_member'
                        AND EXISTS (
                            SELECT 1
                            FROM reports r
                            WHERE r.id = report_sections.report_id
                                AND r.npo_engagement_id::text = fsa_current_npo_engagement_id()
                                AND fsa_user_is_board_member_of(r.npo_engagement_id)
                        )
                    )
                    OR (
                        fsa_current_role() = 'funder_contact'
                        AND EXISTS (
                            SELECT 1
                            FROM reports r
                            WHERE r.id = report_sections.report_id
                                AND r.id::text = fsa_current_report_id()
                                AND r.type = '{$funderReport}'
                                AND r.review_status = 'reviewed'
                                AND r.reviewed_at IS NOT NULL
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = report_sections.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );
        SQL);
    }

    private function restoreLegacyPolicies(): void
    {
        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS npo_engagements_client_scope ON npo_engagements;
            CREATE POLICY npo_engagements_client_scope ON npo_engagements
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            DROP POLICY IF EXISTS npo_dimension_scores_client_scope ON npo_dimension_scores;
            CREATE POLICY npo_dimension_scores_client_scope ON npo_dimension_scores
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            DROP POLICY IF EXISTS milestones_client_scope ON milestones;
            CREATE POLICY milestones_client_scope ON milestones
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            DROP POLICY IF EXISTS milestone_actions_client_scope ON milestone_actions;
            CREATE POLICY milestone_actions_client_scope ON milestone_actions
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            DROP POLICY IF EXISTS reports_client_scope ON reports;
            CREATE POLICY reports_client_scope ON reports
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = reports.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = reports.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            DROP POLICY IF EXISTS report_sections_client_scope ON report_sections;
            CREATE POLICY report_sections_client_scope ON report_sections
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = report_sections.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = report_sections.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );
        SQL);
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
