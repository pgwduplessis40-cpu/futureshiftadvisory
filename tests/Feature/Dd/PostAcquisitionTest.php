<?php

declare(strict_types=1);

namespace Tests\Feature\Dd;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\FindingSeverity;
use App\Enums\PvType;
use App\Enums\QuestionnaireSet;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\BusinessPlan;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\DdDataRoomItem;
use App\Models\DdEngagement;
use App\Models\DdValuation;
use App\Models\DdWorkstream;
use App\Models\Document;
use App\Models\PvCalculation;
use App\Models\QuestionnaireResponse;
use App\Models\Report;
use App\Models\User;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Dd\DdOnboarding;
use App\Services\Dd\PostAcquisition;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pptx\Contracts\PptxGenerator;
use App\Support\RequestContext;
use Database\Seeders\DdSpecificQuestionnaireSeeder;
use Database\Seeders\PostAcquisitionGapQuestionnaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PostAcquisitionTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_post_acquisition_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DdSpecificQuestionnaireSeeder::class);
        $this->seed(PostAcquisitionGapQuestionnaireSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');

        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });
        $this->app->instance(PptxGenerator::class, new class implements PptxGenerator
        {
            public function render(Report $report): string
            {
                return "PPTX\n".$report->title;
            }
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->connectionBypassesRls = $this->currentRoleBypassesRls();

            if ($this->connectionBypassesRls) {
                $this->createNonBypassRole();
            }
        }
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT ON post_acquisition_migrations FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_post_acquisition_conversion_creates_advisory_client_and_migrates_dd_documents(): void
    {
        [$advisor, $engagement, $plan] = $this->readyEngagement();
        $sourceDocument = $this->dataRoomItem($engagement, 'legal')->document;
        $this->ddValuation($engagement, 650000);
        $this->finding($engagement, 'legal', FindingSeverity::High, 'Assignment consent risk', 'Customer assignment consent is required.');

        $migration = app(PostAcquisition::class)->convert($engagement, $advisor);

        $this->assertSame($plan->id, $migration->business_plan_id);
        $this->assertSame($engagement->id, $migration->dd_engagement_id);
        $this->assertSame($engagement->client_id, $migration->buyer_client_id);
        $this->assertSame(650000.0, $migration->dd_pv_baseline);
        $this->assertSame('Sourced from DD', $migration->metadata['source_label']);
        $this->assertSame(EngagementType::POST_ACQUISITION_ADVISORY, $migration->advisoryClient->engagement_type);
        $this->assertSame($engagement->target_name, $migration->advisoryClient->legal_name);

        $migratedDocument = Document::query()->where('client_id', $migration->advisory_client_id)->firstOrFail();
        $this->assertStringStartsWith('Sourced from DD - ', $migratedDocument->original_filename);
        $this->assertNotSame($sourceDocument?->stored_path, $migratedDocument->stored_path);
        $this->assertSame('Sourced from DD', $migratedDocument->scanner_payload['source_label']);
        $this->assertSame($sourceDocument?->stored_path, $migratedDocument->scanner_payload['source_stored_path']);
        $this->assertContains($migratedDocument->id, $migration->migrated_document_ids);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $migration->advisory_client_id,
            'user_id' => $advisor->getKey(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'dd.post_acquisition_created',
            'subject_id' => $migration->id,
        ]);
    }

    public function test_gap_questionnaire_is_prefilled_from_dd_and_leaves_only_remaining_gaps(): void
    {
        [$advisor, $engagement] = $this->readyEngagement('gap-dd-advisor@example.test');
        $this->dataRoomItem($engagement, 'financial');
        $this->ddValuation($engagement, 500000);
        $this->finding($engagement, 'financial', FindingSeverity::High, 'Working capital risk', 'Completion accounts need a working-capital true-up.');

        $migration = app(PostAcquisition::class)->convert($engagement, $advisor);
        /** @var QuestionnaireResponse $response */
        $response = $migration->gapQuestionnaireResponse()->with('questionnaire', 'answers.question')->firstOrFail();

        $this->assertSame(QuestionnaireSet::POST_ACQUISITION_GAP, $response->questionnaire?->set);
        $this->assertNull($response->submitted_at);
        $this->assertCount(3, $response->answers);
        $this->assertNotSame([], $migration->metadata['gap_questions_remaining']);
        $answerText = $response->answers
            ->map(fn ($answer): string => (string) data_get($answer->value, 'text'))
            ->implode("\n");
        $this->assertStringContainsString('Target Supplies Limited', $answerText);
        $this->assertStringContainsString('Working capital risk', $answerText);
        $this->assertStringContainsString('Sourced from DD', $answerText);
    }

    public function test_auto_generated_post_acquisition_proposal_carries_dd_pv_baseline(): void
    {
        [$advisor, $engagement] = $this->readyEngagement('proposal-dd-advisor@example.test');
        $this->dataRoomItem($engagement, 'valuation');
        $this->ddValuation($engagement, 750000);
        $this->finding($engagement, 'valuation', FindingSeverity::Medium, 'Earnout sensitivity', 'Earnout assumptions should be monitored.');

        $migration = app(PostAcquisition::class)->convert($engagement, $advisor);
        $proposal = $migration->proposal()->with('feeCalculation')->firstOrFail();

        $this->assertSame($migration->advisory_client_id, $proposal->client_id);
        $this->assertSame(FeeMethod::OutcomeBased, $proposal->feeCalculation?->method);
        $this->assertSame(750000.0, $proposal->feeCalculation?->improvement_pv_total);
        $this->assertSame(750000, data_get($proposal->feeCalculation?->inputs, 'dd_pv_baseline'));
        $this->assertSame($migration->dd_report_id, data_get($proposal->services, '0.dd_report_id'));
        $this->assertStringContainsString('DD-derived PV baseline NZD 750,000', (string) data_get($proposal->scope, 'summary'));
        $this->assertGreaterThan(0, $proposal->roi_ratio);
    }

    public function test_post_acquisition_migrations_are_isolated_by_buyer_or_advisory_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Post-acquisition RLS assertions require Postgres.');
        }

        [$advisorA, $engagementA] = $this->readyEngagement('post-acq-rls-a@example.test');
        $this->dataRoomItem($engagementA, 'financial');
        $this->ddValuation($engagementA, 500000);
        $this->finding($engagementA, 'financial', FindingSeverity::High, 'A scoped risk', 'A scoped risk body.');
        $migrationA = app(PostAcquisition::class)->convert($engagementA, $advisorA);

        [$advisorB, $engagementB] = $this->readyEngagement('post-acq-rls-b@example.test');
        $this->dataRoomItem($engagementB, 'financial');
        $this->ddValuation($engagementB, 500000);
        $this->finding($engagementB, 'financial', FindingSeverity::High, 'B scoped risk', 'B scoped risk body.');
        $migrationB = app(PostAcquisition::class)->convert($engagementB, $advisorB);

        app(RequestContext::class)->apply('advisor', [(string) $migrationA->buyer_client_id]);

        $visible = $this->withRlsRole(fn (): array => DB::table('post_acquisition_migrations')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $this->assertContains($migrationA->id, $visible);
        $this->assertNotContains($migrationB->id, $visible);

        app(RequestContext::class)->apply('advisor', [(string) $migrationA->advisory_client_id]);
        $visibleViaAdvisoryClient = $this->withRlsRole(fn (): array => DB::table('post_acquisition_migrations')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $this->assertContains($migrationA->id, $visibleViaAdvisoryClient);
        $this->assertNotContains($migrationB->id, $visibleViaAdvisoryClient);
    }

    /**
     * @return array{0: User, 1: DdEngagement, 2: BusinessPlan}
     */
    private function readyEngagement(string $advisorEmail = 'post-acq-dd-advisor@example.test'): array
    {
        [$advisor, $engagement] = $this->ddEngagement($advisorEmail);
        $engagement->forceFill([
            'status' => DdEngagement::STATUS_ACQUISITION_PROCEEDING,
            'recommendation' => DdEngagement::RECOMMENDATION_PROCEED,
        ])->save();

        $plan = BusinessPlan::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->id,
            'title' => 'Acquisition plan: '.$engagement->target_name,
            'source_type' => BusinessPlan::SOURCE_DUE_DILIGENCE,
            'status' => BusinessPlan::STATUS_FOUNDING,
            'founding_advisory_payload' => [
                'source' => 'test',
                'target' => $engagement->target_name,
            ],
            'created_by_user_id' => $advisor->getKey(),
            'completed_at' => now(),
        ]);

        return [$advisor, $engagement->refresh()->load('client.teamMembers'), $plan];
    }

    /**
     * @return array{0: User, 1: DdEngagement}
     */
    private function ddEngagement(string $advisorEmail): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::DUE_DILIGENCE,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Buyer Holdings Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::DUE_DILIGENCE->value],
        ]);

        $conflict = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::DUE_DILIGENCE,
            existingRelationship: false,
        );

        $engagement = app(DdOnboarding::class)->start(
            buyer: $client,
            advisor: $advisor,
            conflict: $conflict,
            targetName: 'Target Supplies Limited',
            targetDetails: [
                'nzbn' => '9429000000999',
                'industry' => 'Distribution',
            ],
        );

        return [$advisor, $engagement];
    }

    private function dataRoomItem(DdEngagement $engagement, string $workstream): DdDataRoomItem
    {
        $document = Document::query()->create([
            'client_id' => $engagement->client_id,
            'category' => Document::CATEGORY_DD_ARTIFACT,
            'original_filename' => "{$workstream}.txt",
            'stored_path' => 'post-acquisition/'.Str::uuid().".{$workstream}.txt",
            'byte_size' => 128,
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', $workstream.$engagement->id),
            'scanner_result' => Document::SCANNER_CLEAN,
            'scanner_payload' => ['engine' => 'fixture'],
        ]);

        return DdDataRoomItem::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->id,
            'document_id' => $document->id,
            'workstream' => $workstream,
            'folder' => 'general',
            'artifact_type' => DdDataRoomItem::ARTIFACT_TYPE,
            'source' => DdDataRoomItem::SOURCE_GUEST_UPLOAD,
        ])->load('document');
    }

    private function finding(
        DdEngagement $engagement,
        string $workstream,
        FindingSeverity $severity,
        string $title,
        string $body,
    ): AnalysisFinding {
        $run = AnalysisRun::query()->create([
            'client_id' => $engagement->client_id,
            'module' => AnalysisModule::DdWorkstream,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => AnalysisLens::values(),
            'data_quality_snapshot' => ['level' => Client::DATA_QUALITY_LOW],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        DdWorkstream::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->id,
            'workstream' => $workstream,
            'status' => DdWorkstream::STATUS_COMPLETED,
            'analysis_run_id' => $run->id,
            'data_room_item_ids' => [],
            'verification_weight' => 2,
            'nz_checks' => [],
            'ran_at' => now(),
        ]);

        return AnalysisFinding::query()->create([
            'analysis_run_id' => $run->id,
            'client_id' => $engagement->client_id,
            'lens' => AnalysisLens::Diagnostic,
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'attributions' => [
                ['claim' => $title, 'source_reference' => 'post_acq_test:'.$workstream],
            ],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED,
            'uncertainty' => Uncertainty::Low,
            'data_quality_disclaimer' => 'Data quality note: fixture.',
            'bias_signals' => [],
        ]);
    }

    private function ddValuation(DdEngagement $engagement, float $mid): DdValuation
    {
        $calculation = PvCalculation::query()->create([
            'client_id' => $engagement->client_id,
            'type' => PvType::BusinessValuation,
            'discount_method' => DiscountMethod::AdvisorConfigured,
            'discount_rate' => 0.12,
            'discount_rate_rationale' => 'Post-acquisition fixture valuation rate.',
            'inputs' => ['fixture' => true],
            'result' => ['present_value' => $mid],
            'as_at' => now(),
            'source_attributions' => [
                ['claim' => 'Fixture valuation', 'source_reference' => 'post_acq_test:valuation'],
            ],
        ]);

        $businessValuation = BusinessValuation::query()->create([
            'client_id' => $engagement->client_id,
            'pv_calculation_id' => $calculation->id,
            'sde_value' => ['mid' => $mid * 0.95],
            'ebitda_value' => ['mid' => $mid],
            'dcf_value' => ['mid' => $mid * 1.05],
            'reconciled_low' => $mid * 0.9,
            'reconciled_mid' => $mid,
            'reconciled_high' => $mid * 1.1,
            'adjustments' => [],
            'source_attributions' => [
                ['claim' => 'Fixture valuation', 'source_reference' => 'post_acq_test:business_valuation'],
            ],
            'as_at' => now(),
        ]);

        return DdValuation::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->id,
            'business_valuation_id' => $businessValuation->id,
            'pv_calculation_id' => $calculation->id,
            'source_currency' => 'NZD',
            'normalised_currency' => 'NZD',
            'source_to_nzd_rate' => 1,
            'normalised_values' => [
                'reconciled' => [
                    'low' => $mid * 0.9,
                    'mid' => $mid,
                    'high' => $mid * 1.1,
                ],
            ],
            'sensitivity' => [
                'base_rate' => ['reconciled' => ['mid' => $mid]],
            ],
            'buyer_position' => [
                'position' => 'within_range',
            ],
            'source_attributions' => [
                ['claim' => 'Fixture valuation', 'source_reference' => 'post_acq_test:valuation'],
            ],
            'as_at' => now(),
        ]);
    }

    private function currentRoleBypassesRls(): bool
    {
        $role = DB::selectOne(
            'SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user'
        );

        return (bool) ($role->rolsuper ?? false) || (bool) ($role->rolbypassrls ?? false);
    }

    private function createNonBypassRole(): void
    {
        DB::unprepared(sprintf(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '%1$s') THEN
                    CREATE ROLE %1$s NOLOGIN NOBYPASSRLS;
                END IF;
            END
            $$;

            GRANT USAGE ON SCHEMA public TO %1$s;
            GRANT SELECT ON post_acquisition_migrations TO %1$s;
        SQL, self::RLS_APP_ROLE));
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    private function withRlsRole(callable $callback): mixed
    {
        if (! $this->connectionBypassesRls) {
            return $callback();
        }

        DB::statement('SET ROLE '.self::RLS_APP_ROLE);
        $usesSavepoint = DB::transactionLevel() > 0;

        if ($usesSavepoint) {
            DB::statement('SAVEPOINT post_acquisition_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT post_acquisition_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT post_acquisition_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
