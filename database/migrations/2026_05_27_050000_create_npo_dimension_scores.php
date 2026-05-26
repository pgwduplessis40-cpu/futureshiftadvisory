<?php

declare(strict_types=1);

use App\Enums\NpoTiritiMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npo_dimension_scores', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->uuid('npo_engagement_id');
            $table->uuid('assessment_batch_id');
            $table->unsignedTinyInteger('dimension_number');
            $table->string('dimension_key', 80);
            $table->string('dimension_label', 120);
            $table->string('tiriti_mode', 24);
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('advisor_weight');
            $table->decimal('weighted_score', 6, 2);
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->jsonb('findings');
            $table->jsonb('mode_b_criteria_contributions')->nullable();
            $table->jsonb('source_attributions');
            $table->jsonb('scoring_context');
            $table->string('source', 80)->default('advisor_assessment');
            $table->uuid('source_npo_engagement_id')->nullable();
            $table->timestampTz('captured_at');
            $table->timestampsTz();

            $table->unique(['npo_engagement_id', 'assessment_batch_id', 'dimension_number'], 'npo_dimension_scores_batch_dimension_unique');
            $table->index(['client_id', 'captured_at', 'assessment_batch_id'], 'npo_dimension_scores_latest_batch_index');
            $table->index(['npo_engagement_id', 'dimension_number']);
            $table->index(['source', 'source_npo_engagement_id']);
        });

        if ($this->onPostgres()) {
            DB::statement(<<<'SQL'
                ALTER TABLE npo_dimension_scores
                ADD CONSTRAINT npo_dimension_scores_engagement_client_fk
                FOREIGN KEY (npo_engagement_id, client_id)
                REFERENCES npo_engagements (id, client_id)
                ON DELETE CASCADE
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE npo_dimension_scores
                ADD CONSTRAINT npo_dimension_scores_source_engagement_fk
                FOREIGN KEY (source_npo_engagement_id)
                REFERENCES npo_engagements (id)
                ON DELETE SET NULL
            SQL);
        }

        $this->installChecks();
        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('npo_dimension_scores');
    }

    private function installChecks(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        $tiritiModes = collect(NpoTiritiMode::cases())
            ->map(static fn (NpoTiritiMode $mode): string => "'".$mode->value."'")
            ->implode(', ');

        DB::unprepared(<<<SQL
            ALTER TABLE npo_dimension_scores
                ADD CONSTRAINT npo_dimension_scores_dimension_number_check
                    CHECK (dimension_number BETWEEN 1 AND 8),
                ADD CONSTRAINT npo_dimension_scores_tiriti_mode_check
                    CHECK (tiriti_mode IN ({$tiritiModes})),
                ADD CONSTRAINT npo_dimension_scores_score_check
                    CHECK (score BETWEEN 0 AND 100),
                ADD CONSTRAINT npo_dimension_scores_weight_check
                    CHECK (advisor_weight BETWEEN 0 AND 100),
                ADD CONSTRAINT npo_dimension_scores_health_score_check
                    CHECK (health_score IS NULL OR health_score BETWEEN 0 AND 100),
                ADD CONSTRAINT npo_dimension_scores_source_check
                    CHECK (source IN ('advisor_assessment', 'governance_review_prepopulation'));
        SQL);
    }

    private function installRlsPolicies(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE npo_dimension_scores ENABLE ROW LEVEL SECURITY;
            ALTER TABLE npo_dimension_scores FORCE ROW LEVEL SECURITY;

            CREATE POLICY npo_dimension_scores_client_scope ON npo_dimension_scores
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

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
