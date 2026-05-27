<?php

declare(strict_types=1);

use App\Models\Document;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->uuid('npo_engagement_id')->nullable()->after('client_id');
            $table->index(['npo_engagement_id', 'category'], 'documents_npo_engagement_category_idx');
        });

        Schema::create('npo_impact_metrics', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->uuid('npo_engagement_id');
            $table->string('metric_key', 120);
            $table->string('metric_label', 160);
            $table->decimal('value', 16, 2);
            $table->string('unit', 40)->nullable();
            $table->decimal('platform_value', 16, 2)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('source', 80)->default('client_portal');
            $table->text('notes')->nullable();
            $table->foreignId('entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'metric_key']);
            $table->index(['npo_engagement_id', 'metric_key', 'period_end'], 'npo_impact_metrics_engagement_metric_period_idx');
        });

        if ($this->onPostgres()) {
            DB::statement(<<<'SQL'
                ALTER TABLE documents
                ADD CONSTRAINT documents_npo_engagement_client_fk
                FOREIGN KEY (npo_engagement_id, client_id)
                REFERENCES npo_engagements (id, client_id)
                ON DELETE CASCADE
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE npo_impact_metrics
                ADD CONSTRAINT npo_impact_metrics_engagement_client_fk
                FOREIGN KEY (npo_engagement_id, client_id)
                REFERENCES npo_engagements (id, client_id)
                ON DELETE CASCADE
            SQL);

            DB::statement('ALTER TABLE npo_impact_metrics ADD CONSTRAINT npo_impact_metrics_non_negative_check CHECK (value >= 0 AND (platform_value IS NULL OR platform_value >= 0))');
        }

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_npo_engagement_client_fk');
            DB::statement('DROP POLICY IF EXISTS npo_impact_metrics_client_scope ON npo_impact_metrics');
            $this->restoreDocumentPolicy();
        }

        Schema::dropIfExists('npo_impact_metrics');

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_npo_engagement_category_idx');
            $table->dropColumn('npo_engagement_id');
        });
    }

    private function installRlsPolicies(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        $meetingMinutes = Document::CATEGORY_NPO_MEETING_MINUTES;
        $boardRecord = Document::CATEGORY_NPO_BOARD_RECORD;

        DB::unprepared(<<<SQL
            DROP POLICY IF EXISTS documents_scope ON documents;
            DROP POLICY IF EXISTS documents_client_scope ON documents;

            CREATE POLICY documents_scope ON documents
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = documents.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                    OR (
                        fsa_current_role() = 'npo_board_member'
                        AND npo_engagement_id::text = fsa_current_npo_engagement_id()
                        AND category IN ('{$meetingMinutes}', '{$boardRecord}')
                        AND fsa_user_is_board_member_of(npo_engagement_id)
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = documents.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                );

            ALTER TABLE npo_impact_metrics ENABLE ROW LEVEL SECURITY;
            ALTER TABLE npo_impact_metrics FORCE ROW LEVEL SECURITY;

            CREATE POLICY npo_impact_metrics_client_scope ON npo_impact_metrics
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
        SQL);
    }

    private function restoreDocumentPolicy(): void
    {
        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS documents_scope ON documents;
            DROP POLICY IF EXISTS documents_client_scope ON documents;

            CREATE POLICY documents_scope ON documents
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = documents.entrepreneur_profile_id
                        AND (
                            entrepreneur_profiles.assigned_advisor_id::text = fsa_current_user_id()
                            OR entrepreneur_profiles.user_id::text = fsa_current_user_id()
                        )
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM entrepreneur_profiles
                        WHERE entrepreneur_profiles.id = documents.entrepreneur_profile_id
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
