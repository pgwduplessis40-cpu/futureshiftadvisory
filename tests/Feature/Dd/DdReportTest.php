<?php

declare(strict_types=1);

namespace Tests\Feature\Dd;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Enums\PvType;
use App\Enums\QuestionnaireSet;
use App\Enums\ReportType;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\DdDataRoomItem;
use App\Models\DdEngagement;
use App\Models\DdRiskRegisterItem;
use App\Models\DdValuation;
use App\Models\DdWorkstream;
use App\Models\Document;
use App\Models\PvCalculation;
use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use App\Models\Report;
use App\Models\User;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Dd\DdAdviceReportGenerator;
use App\Services\Dd\DdDisclaimer;
use App\Services\Dd\DdOnboarding;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pptx\Contracts\PptxGenerator;
use App\Services\Reports\ReportComposer;
use App\Support\RequestContext;
use Database\Seeders\DdSpecificQuestionnaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DdReportTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_dd_report_rls_app';

    private object $renderer;

    private object $pptx;

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DdSpecificQuestionnaireSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');

        $this->renderer = new class implements PdfRenderer
        {
            public string $html = '';

            public function render(string $html): string
            {
                $this->html = $html;

                return "%PDF-1.4\n".strip_tags($html);
            }
        };
        $this->app->instance(PdfRenderer::class, $this->renderer);

        $this->pptx = new class implements PptxGenerator
        {
            public string $payload = '';

            public function render(Report $report): string
            {
                $this->payload = $report->title."\n".$report->sections->pluck('title')->implode("\n");

                return "PPTX\n".$this->payload;
            }
        };
        $this->app->instance(PptxGenerator::class, $this->pptx);

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
                DB::statement('REVOKE SELECT ON dd_risk_register, dd_integration_plans FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_due_diligence_report_includes_required_sections_disclaimer_pdf_and_powerpoint(): void
    {
        [$advisor, $engagement] = $this->ddEngagement();
        $this->ddValuation($engagement, 600000);
        $this->finding($engagement, 'financial', FindingSeverity::High, 'EBITDA quality risk', 'Normalised EBITDA depends on one-off addbacks.');
        $this->finding($engagement, 'legal', FindingSeverity::Medium, 'Contract assignment risk', 'Key customer contracts need consent before completion.');

        $report = app(ReportComposer::class)->composeDueDiligence($engagement, $advisor);

        $this->assertSame(ReportType::DueDiligence, $report->type);
        $this->assertNotNull($report->pdf_path);
        $this->assertNotNull($report->pptx_path);
        Storage::disk('secure_local')->assertExists($report->pdf_path);
        Storage::disk('secure_local')->assertExists($report->pptx_path);
        $this->assertSame($engagement->id, $report->metadata['dd_engagement_id']);
        $this->assertSame(DdEngagement::RECOMMENDATION_RENEGOTIATE, $engagement->refresh()->recommendation);

        foreach ([
            'dd_executive_summary',
            'dd_valuation',
            'dd_purchase_price_range',
            'dd_workstream_findings',
            'dd_risk_register',
            'dd_price_adjustment',
            'dd_integration_plan',
            'dd_buyer_readiness',
            'dd_recommendation',
            'dd_liability_disclaimer',
        ] as $key) {
            $this->assertTrue($report->sections->contains('key', $key), "Missing DD report section {$key}.");
        }

        $this->assertStringContainsString(DdDisclaimer::STANDARD, $this->renderer->html);
        $this->assertStringContainsString('Primary basis: Discounted Cash Flow (DCF)', $this->renderer->html);
        $this->assertStringContainsString('Liability disclaimer', $this->pptx->payload);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'dd.report_generated',
            'subject_id' => $report->id,
        ]);
    }

    public function test_acquisition_go_no_go_report_surfaces_walk_away_price_chips(): void
    {
        [$advisor, $engagement] = $this->ddEngagement('go-no-go-dd-advisor@example.test');
        $engagement->forceFill([
            'target_details' => [
                ...$engagement->target_details,
                'asking_price' => 820000,
                'gst_going_concern_zero_rating' => false,
                'working_capital_adjustment_nzd' => 25000,
                'holidays_act' => [
                    'underpaid_hours' => 120,
                    'hourly_rate' => 38,
                    'buffer_rate' => 0.15,
                ],
                'working_capital_peg' => 'Completion accounts to peg normal working capital at NZD 140,000.',
                'vendor_finance' => '10 percent vendor finance proposed.',
                'earnout' => 'Earn-out linked to retained customer revenue.',
            ],
        ])->save();
        $this->ddValuation($engagement, 650000);
        $this->finding($engagement, 'financial', FindingSeverity::High, 'Stock ageing risk', 'Aged inventory should be adjusted at completion.');

        $report = app(ReportComposer::class)->composeAcquisitionGoNoGo($engagement, $advisor);

        $this->assertSame(ReportType::AcquisitionGoNoGo, $report->type);
        $this->assertSame('pending_review', $report->review_status);
        Storage::disk('secure_local')->assertExists($report->pdf_path);

        foreach ([
            'go_no_go_decision',
            'walk_away_price_chips',
            'deal_mechanics',
            'go_no_go_evidence',
        ] as $key) {
            $this->assertTrue($report->sections->contains('key', $key), "Missing Go/No-Go section {$key}.");
        }

        $walkAway = $report->sections->firstWhere('key', 'walk_away_price_chips')?->metadata['walk_away_price'];
        $this->assertGreaterThan(0, $walkAway['risk_adjustment_nzd']);
        $this->assertGreaterThan(0, $walkAway['holidays_act_liability_nzd']);
        $this->assertGreaterThan(0, $walkAway['working_capital_adjustment_nzd']);
        $this->assertEqualsWithDelta(820000.0, $walkAway['asking_price_nzd'], 0.01);
        $this->assertStringContainsString('Walk-away price and red-flag price chips', $this->renderer->html);
        $this->assertStringContainsString('GST going-concern zero-rating', $this->renderer->html);
    }

    public function test_dd_risk_register_is_ranked_by_pv_cost_and_feeds_price_adjustment(): void
    {
        [$advisor, $engagement] = $this->ddEngagement('risk-ranking-dd-advisor@example.test');
        $this->ddValuation($engagement, 800000);
        $this->finding($engagement, 'tax', FindingSeverity::Medium, 'GST exposure', 'GST treatment needs review.');
        $this->finding($engagement, 'legal', FindingSeverity::Critical, 'Title defect', 'Property title defect could block settlement.');
        $this->finding($engagement, 'operational', FindingSeverity::Low, 'Warehouse process note', 'Warehouse process needs onboarding notes.');

        $report = app(ReportComposer::class)->composeDueDiligence($engagement, $advisor);
        $risks = DdRiskRegisterItem::query()
            ->where('dd_engagement_id', $engagement->id)
            ->orderBy('rank')
            ->get();

        $this->assertCount(3, $risks);
        $this->assertSame(DdRiskRegisterItem::LEVEL_DEAL_KILLER, $risks->first()?->risk_level);
        $this->assertGreaterThanOrEqual($risks[1]->pv_of_cost, $risks[0]->pv_of_cost);
        $this->assertSame([1, 2, 3], $risks->pluck('rank')->all());

        $priceSection = $report->sections->firstWhere('key', 'dd_price_adjustment');
        $this->assertNotNull($priceSection);
        $this->assertEqualsWithDelta(
            $risks->sum('price_adjustment_nzd'),
            $priceSection->metadata['total_price_adjustment_nzd'],
            0.01,
        );

        $purchaseRangeSection = $report->sections->firstWhere('key', 'dd_purchase_price_range');
        $this->assertNotNull($purchaseRangeSection);
        $this->assertEqualsWithDelta(
            $risks->sum('price_adjustment_nzd'),
            $purchaseRangeSection->metadata['due_diligence_risk_adjustment_nzd'],
            0.01,
        );
        $this->assertLessThan(
            $purchaseRangeSection->metadata['dcf_range_nzd']['mid'],
            $purchaseRangeSection->metadata['purchase_price_range_nzd']['mid'],
        );
    }

    public function test_due_diligence_recommendation_paths_are_proceed_renegotiate_and_abandon(): void
    {
        [$advisor, $proceed] = $this->ddEngagement('proceed-dd-advisor@example.test');
        $this->ddValuation($proceed, 500000);
        $this->finding($proceed, 'commercial_market', FindingSeverity::Low, 'Market note', 'Market concentration is manageable.');

        [$renegotiateAdvisor, $renegotiate] = $this->ddEngagement('renegotiate-dd-advisor@example.test');
        $this->ddValuation($renegotiate, 500000, 'renegotiate_or_walkaway');
        $this->finding($renegotiate, 'financial', FindingSeverity::Medium, 'Working capital risk', 'Working capital needs completion adjustment.');

        [$abandonAdvisor, $abandon] = $this->ddEngagement('abandon-dd-advisor@example.test');
        $this->ddValuation($abandon, 500000);
        $this->finding($abandon, 'legal', FindingSeverity::Critical, 'Unresolved litigation', 'Litigation exposure is not quantified.');

        app(ReportComposer::class)->composeDueDiligence($proceed, $advisor);
        app(ReportComposer::class)->composeDueDiligence($renegotiate, $renegotiateAdvisor);
        app(ReportComposer::class)->composeDueDiligence($abandon, $abandonAdvisor);

        $this->assertSame(DdEngagement::RECOMMENDATION_PROCEED, $proceed->refresh()->recommendation);
        $this->assertSame(DdEngagement::RECOMMENDATION_RENEGOTIATE, $renegotiate->refresh()->recommendation);
        $this->assertSame(DdEngagement::RECOMMENDATION_ABANDON, $abandon->refresh()->recommendation);
    }

    public function test_dd_advice_report_generates_when_questionnaire_and_data_room_upload_are_ready(): void
    {
        [$advisor, $engagement] = $this->ddEngagement('ready-dd-advisor@example.test');
        $this->ddQuestionnaireResponse($engagement, $advisor);
        $this->dataRoomItem($engagement);
        $this->ddValuation($engagement, 700000);

        $report = app(DdAdviceReportGenerator::class)->generateIfReady($engagement, $advisor);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertTrue($report->sections->contains('key', 'dd_purchase_price_range'));
        $this->assertSame(
            'dcf',
            $report->sections->firstWhere('key', 'dd_purchase_price_range')?->metadata['primary_method'],
        );

        $this->assertNull(app(DdAdviceReportGenerator::class)->generateIfReady($engagement, $advisor));

        $reports = Report::query()
            ->where('client_id', $engagement->client_id)
            ->where('type', ReportType::DueDiligence)
            ->get()
            ->filter(fn (Report $report): bool => (string) data_get($report->metadata, 'dd_engagement_id') === (string) $engagement->id);

        $this->assertCount(1, $reports);
    }

    public function test_dd_report_tables_are_isolated_by_buyer_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('DD report RLS assertions require Postgres.');
        }

        [$advisorA, $engagementA] = $this->ddEngagement('dd-report-rls-a@example.test');
        $this->ddValuation($engagementA, 500000);
        $this->finding($engagementA, 'financial', FindingSeverity::High, 'Scoped A risk', 'A scoped risk.');
        app(ReportComposer::class)->composeDueDiligence($engagementA, $advisorA);

        [$advisorB, $engagementB] = $this->ddEngagement('dd-report-rls-b@example.test');
        $this->ddValuation($engagementB, 500000);
        $this->finding($engagementB, 'financial', FindingSeverity::High, 'Scoped B risk', 'B scoped risk.');
        app(ReportComposer::class)->composeDueDiligence($engagementB, $advisorB);

        app(RequestContext::class)->apply('advisor', [(string) $engagementA->client_id]);

        foreach (['dd_risk_register', 'dd_integration_plans'] as $table) {
            $visibleClientIds = $this->withRlsRole(fn (): array => DB::table($table)
                ->pluck('client_id')
                ->map(fn (mixed $id): string => (string) $id)
                ->unique()
                ->values()
                ->all());

            $this->assertContains($engagementA->client_id, $visibleClientIds, "{$table} should expose the scoped DD buyer.");
            $this->assertNotContains($engagementB->client_id, $visibleClientIds, "{$table} should hide other DD buyers.");
        }
    }

    /**
     * @return array{0: User, 1: DdEngagement}
     */
    private function ddEngagement(string $advisorEmail = 'dd-report-advisor@example.test'): array
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
                ['claim' => $title, 'source_reference' => 'dd_test:'.$workstream],
            ],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED,
            'uncertainty' => Uncertainty::Low,
            'data_quality_disclaimer' => 'Data quality note: DD fixture quality is low.',
            'bias_signals' => [],
        ]);
    }

    private function ddValuation(DdEngagement $engagement, float $mid, string $buyerPosition = 'within_range'): DdValuation
    {
        $calculation = PvCalculation::query()->create([
            'client_id' => $engagement->client_id,
            'type' => PvType::BusinessValuation,
            'discount_method' => DiscountMethod::AdvisorConfigured,
            'discount_rate' => 0.12,
            'discount_rate_rationale' => 'DD report fixture valuation rate.',
            'inputs' => ['fixture' => true],
            'result' => ['present_value' => $mid],
            'as_at' => now(),
            'source_attributions' => [
                ['claim' => 'DD fixture valuation', 'source_reference' => 'dd_test:valuation'],
            ],
        ]);

        $businessValuation = BusinessValuation::query()->create([
            'client_id' => $engagement->client_id,
            'pv_calculation_id' => $calculation->id,
            'sde_value' => ['low' => $mid * 0.85, 'mid' => $mid * 0.95, 'high' => $mid * 1.05],
            'ebitda_value' => ['low' => $mid * 0.9, 'mid' => $mid, 'high' => $mid * 1.1],
            'dcf_value' => ['low' => $mid * 0.95, 'mid' => $mid * 1.05, 'high' => $mid * 1.18],
            'reconciled_low' => $mid * 0.9,
            'reconciled_mid' => $mid,
            'reconciled_high' => $mid * 1.1,
            'adjustments' => [],
            'source_attributions' => [
                ['claim' => 'DD fixture business valuation', 'source_reference' => 'dd_test:business_valuation'],
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
                'position' => $buyerPosition,
            ],
            'source_attributions' => [
                ['claim' => 'DD fixture valuation', 'source_reference' => 'dd_test:valuation'],
            ],
            'as_at' => now(),
        ]);
    }

    private function ddQuestionnaireResponse(DdEngagement $engagement, User $advisor): QuestionnaireResponse
    {
        $questionnaire = Questionnaire::query()
            ->forSet(QuestionnaireSet::DUE_DILIGENCE)
            ->published()
            ->firstOrFail();

        return QuestionnaireResponse::query()->create([
            'client_id' => $engagement->client_id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_by_user_id' => $advisor->id,
            'submitted_at' => now(),
        ]);
    }

    private function dataRoomItem(DdEngagement $engagement): DdDataRoomItem
    {
        $document = Document::query()->create([
            'client_id' => $engagement->client_id,
            'category' => Document::CATEGORY_DD_ARTIFACT,
            'original_filename' => 'target-management-accounts.pdf',
            'stored_path' => 'test/dd/target-management-accounts.pdf',
            'byte_size' => 1024,
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', 'target-management-accounts'),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);

        return DdDataRoomItem::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->id,
            'document_id' => $document->id,
            'workstream' => 'financial',
            'folder' => 'management_accounts',
            'artifact_type' => DdDataRoomItem::ARTIFACT_TYPE,
            'source' => DdDataRoomItem::SOURCE_GUEST_UPLOAD,
            'metadata' => ['fixture' => true],
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
            GRANT SELECT ON dd_risk_register, dd_integration_plans TO %1$s;
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
            DB::statement('SAVEPOINT dd_report_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT dd_report_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT dd_report_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
