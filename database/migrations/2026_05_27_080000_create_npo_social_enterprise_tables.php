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
        Schema::create('npo_social_enterprise_scorecards', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->uuid('npo_engagement_id');
            $table->unsignedTinyInteger('commercial_score');
            $table->unsignedTinyInteger('mission_score');
            $table->unsignedTinyInteger('commercial_weight');
            $table->unsignedTinyInteger('mission_weight');
            $table->decimal('blended_score', 5, 2);
            $table->jsonb('commercial_axes');
            $table->jsonb('mission_axes');
            $table->jsonb('source_attributions');
            $table->timestampTz('calculated_at');
            $table->timestampsTz();

            $table->index(['client_id', 'calculated_at']);
            $table->index(['npo_engagement_id', 'calculated_at'], 'npo_se_scorecards_engagement_at_idx');
        });

        Schema::create('npo_tension_analyses', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->uuid('npo_engagement_id');
            $table->foreignUuid('npo_social_enterprise_scorecard_id')
                ->constrained('npo_social_enterprise_scorecards')
                ->cascadeOnDelete();
            $table->string('review_status', 40)->default('pending');
            $table->jsonb('tensions');
            $table->jsonb('ai_response');
            $table->jsonb('source_attributions');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('generated_at');
            $table->timestampsTz();

            $table->index(['client_id', 'review_status', 'generated_at']);
            $table->index(['npo_engagement_id', 'generated_at'], 'npo_tensions_engagement_generated_idx');
        });

        if ($this->onPostgres()) {
            foreach (['npo_social_enterprise_scorecards', 'npo_tension_analyses'] as $table) {
                DB::statement(<<<SQL
                    ALTER TABLE {$table}
                    ADD CONSTRAINT {$table}_engagement_client_fk
                    FOREIGN KEY (npo_engagement_id, client_id)
                    REFERENCES npo_engagements (id, client_id)
                    ON DELETE CASCADE
                SQL);
            }

            DB::statement(<<<'SQL'
                ALTER TABLE npo_social_enterprise_scorecards
                ADD CONSTRAINT npo_social_enterprise_scores_check
                    CHECK (commercial_score BETWEEN 0 AND 100 AND mission_score BETWEEN 0 AND 100 AND blended_score BETWEEN 0 AND 100),
                ADD CONSTRAINT npo_social_enterprise_weights_check
                    CHECK (commercial_weight BETWEEN 0 AND 100 AND mission_weight BETWEEN 0 AND 100 AND commercial_weight + mission_weight = 100)
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE npo_tension_analyses
                ADD CONSTRAINT npo_tension_analyses_review_status_check
                    CHECK (review_status IN ('pending', 'reviewed'))
            SQL);
        }

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('npo_tension_analyses');
        Schema::dropIfExists('npo_social_enterprise_scorecards');
    }

    private function installRlsPolicies(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        foreach (['npo_social_enterprise_scorecards', 'npo_tension_analyses'] as $table) {
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

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
