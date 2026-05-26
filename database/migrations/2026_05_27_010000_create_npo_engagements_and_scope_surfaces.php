<?php

declare(strict_types=1);

use App\Enums\NpoConversionStatus;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npo_engagements', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('sub_type', 40);
            $table->string('legal_structure', 80);
            $table->boolean('isa_2022_reregistered')->nullable();
            $table->uuid('converted_from_npo_engagement_id')->nullable();
            $table->string('conversion_status', 40)->nullable();
            $table->text('conversion_decline_reason')->nullable();
            $table->timestampTz('report_delivered_at')->nullable();
            $table->date('reengagement_due_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['id', 'client_id']);
            $table->index(['client_id', 'sub_type']);
            $table->index(['client_id', 'conversion_status']);
            $table->index('converted_from_npo_engagement_id');
        });

        Schema::table('npo_engagements', function (Blueprint $table): void {
            $table->foreign(['converted_from_npo_engagement_id', 'client_id'], 'npo_engagements_conversion_same_client_fk')
                ->references(['id', 'client_id'])
                ->on('npo_engagements')
                ->restrictOnDelete();
        });

        foreach (['reports', 'questionnaire_responses', 'proposals', 'fee_calculations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreignUuid('npo_engagement_id')->nullable()->after('client_id');
                $table->index('npo_engagement_id', "{$tableName}_npo_engagement_id_index");
                $table->foreign(['npo_engagement_id', 'client_id'], "{$tableName}_npo_engagement_same_client_fk")
                    ->references(['id', 'client_id'])
                    ->on('npo_engagements')
                    ->restrictOnDelete();
            });
        }

        Schema::table('questionnaire_responses', function (Blueprint $table): void {
            $table->dropUnique('questionnaire_responses_client_id_questionnaire_id_unique');
        });

        $this->installChecks();
        $this->installQuestionnaireResponseIndexes();
        $this->installRlsPolicies();
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::statement('DROP POLICY IF EXISTS npo_engagements_client_scope ON npo_engagements');
            DB::statement('DROP INDEX IF EXISTS questionnaire_responses_client_questionnaire_legacy_unique');
            DB::statement('DROP INDEX IF EXISTS questionnaire_responses_npo_engagement_questionnaire_unique');
        } else {
            DB::statement('DROP INDEX IF EXISTS questionnaire_responses_client_questionnaire_legacy_unique');
            DB::statement('DROP INDEX IF EXISTS questionnaire_responses_npo_engagement_questionnaire_unique');
        }

        Schema::table('questionnaire_responses', function (Blueprint $table): void {
            $table->unique(['client_id', 'questionnaire_id']);
        });

        foreach (['fee_calculations', 'proposals', 'questionnaire_responses', 'reports'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign("{$tableName}_npo_engagement_same_client_fk");
                $table->dropIndex("{$tableName}_npo_engagement_id_index");
                $table->dropColumn('npo_engagement_id');
            });
        }

        Schema::dropIfExists('npo_engagements');
    }

    private function installChecks(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        $subTypes = $this->quotedValues(array_map(
            static fn (NpoEngagementSubType $type): string => $type->value,
            NpoEngagementSubType::cases(),
        ));
        $legalStructures = $this->quotedValues(array_map(
            static fn (NpoLegalStructure $structure): string => $structure->value,
            NpoLegalStructure::cases(),
        ));
        $conversionStatuses = $this->quotedValues(array_map(
            static fn (NpoConversionStatus $status): string => $status->value,
            NpoConversionStatus::cases(),
        ));

        DB::unprepared(<<<SQL
            ALTER TABLE npo_engagements
                ADD CONSTRAINT npo_engagements_sub_type_check CHECK (sub_type IN ({$subTypes})),
                ADD CONSTRAINT npo_engagements_legal_structure_check CHECK (legal_structure IN ({$legalStructures})),
                ADD CONSTRAINT npo_engagements_conversion_status_check CHECK (conversion_status IS NULL OR conversion_status IN ({$conversionStatuses}));
        SQL);
    }

    private function installQuestionnaireResponseIndexes(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX questionnaire_responses_client_questionnaire_legacy_unique
                ON questionnaire_responses (client_id, questionnaire_id)
                WHERE npo_engagement_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX questionnaire_responses_npo_engagement_questionnaire_unique
                ON questionnaire_responses (npo_engagement_id, questionnaire_id)
                WHERE npo_engagement_id IS NOT NULL
        SQL);
    }

    private function installRlsPolicies(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE npo_engagements ENABLE ROW LEVEL SECURITY;
            ALTER TABLE npo_engagements FORCE ROW LEVEL SECURITY;

            CREATE POLICY npo_engagements_client_scope ON npo_engagements
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

    /**
     * @param  array<int, string>  $values
     */
    private function quotedValues(array $values): string
    {
        return collect($values)
            ->map(static fn (string $value): string => "'".str_replace("'", "''", $value)."'")
            ->implode(', ');
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
