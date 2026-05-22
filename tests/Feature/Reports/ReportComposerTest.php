<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\FindingSeverity;
use App\Enums\ProposalStatus;
use App\Enums\PvType;
use App\Enums\ReportType;
use App\Models\AccountingConnection;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FeeCalculation;
use App\Models\FinancialSnapshot;
use App\Models\Proposal;
use App\Models\PvCalculation;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pptx\Contracts\PptxGenerator;
use App\Services\Reports\ReportComposer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ReportComposerTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_reports_rls_app';

    private object $renderer;

    private object $pptx;

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
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
                DB::statement('REVOKE SELECT ON reports, report_sections FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_client_report_redacts_recommendations_and_fee_detail(): void
    {
        [$advisor, $client] = $this->clientWithTeam();
        $this->businessValuation($client, 500000);
        $this->analysisFixture($client);
        $this->proposal($client);

        $report = app(ReportComposer::class)->compose($client, ReportType::Client, $advisor);

        $this->assertSame(ReportType::Client, $report->type);
        $this->assertNotNull($report->pdf_path);
        Storage::disk('secure_local')->assertExists($report->pdf_path);
        $this->assertFalse($report->sections->contains('lens', AnalysisLens::Prescriptive->value));
        $this->assertFalse($report->sections->contains('key', 'fee_proposal'));
        $this->assertFalse($report->sections->contains('key', 'implementation_plan'));
        $this->assertStringNotContainsString('Fee proposal and ROI', $this->renderer->html);
        $this->assertStringNotContainsString('Implementation plan', $this->renderer->html);

        $report->sections->each(function (ReportSection $section): void {
            $this->assertNotSame([], $section->attributions);
            $this->assertNotSame('', $section->document_support_note);
            $this->assertStringContainsString('Data quality note:', $section->data_quality_note);
        });
    }

    public function test_advisor_report_includes_waterfall_implementation_plan_and_fee_roi(): void
    {
        [$advisor, $client] = $this->clientWithTeam('reports-advisor@example.test');
        $this->businessValuation($client, 650000);
        $this->analysisFixture($client);
        $proposal = $this->proposal($client, 18000, 4.25);

        $report = app(ReportComposer::class)->compose($client, ReportType::Advisor, $advisor);

        $this->assertSame(ReportType::Advisor, $report->type);
        $this->assertTrue($report->sections->contains('key', 'pv_waterfall'));
        $this->assertTrue($report->sections->contains('key', 'implementation_plan'));
        $this->assertTrue($report->sections->contains('key', 'fee_proposal'));
        $this->assertTrue($report->sections->contains('lens', AnalysisLens::Prescriptive->value));
        $this->assertStringContainsString('Future Shift Advisory', $this->renderer->html);
        $this->assertStringContainsString('Fee proposal and ROI', $this->renderer->html);
        $this->assertStringContainsString((string) $proposal->id, ReportSection::query()->where('key', 'fee_proposal')->firstOrFail()->attributions[0]['source_reference']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'report.generated',
            'client_id' => $client->id,
        ]);
    }

    public function test_stakeholder_report_excludes_methodology_and_exports_pdf_and_powerpoint(): void
    {
        [$advisor, $client] = $this->clientWithTeam('stakeholder-advisor@example.test');
        $this->businessValuation($client, 720000);
        $this->analysisFixture($client);
        $this->proposal($client);

        $report = app(ReportComposer::class)->compose($client, ReportType::Stakeholder, $advisor);

        $this->assertSame(ReportType::Stakeholder, $report->type);
        $this->assertNotNull($report->pdf_path);
        $this->assertNotNull($report->pptx_path);
        Storage::disk('secure_local')->assertExists($report->pdf_path);
        Storage::disk('secure_local')->assertExists($report->pptx_path);
        $this->assertGreaterThan(10, $report->pptx_byte_size);
        $this->assertTrue($report->sections->contains('key', 'liability_disclaimer'));
        $this->assertStringContainsString('Liability disclaimer', $this->renderer->html);
        $this->assertStringContainsString('Liability disclaimer', $this->pptx->payload);
        $this->assertStringNotContainsString('FSA methodology', $this->renderer->html);
        $this->assertStringNotContainsString('Future Shift methodology', $this->renderer->html);
        $this->assertSame(['fsa_methodology', 'fsa_ip'], $report->metadata['redactions']);
    }

    public function test_trajectory_report_assembles_trends_pv_milestones_and_requires_review(): void
    {
        [$advisor, $client] = $this->clientWithTeam('trajectory-advisor@example.test');
        $this->financialSnapshot($client, now()->subMonths(9), [
            'revenue' => 100000,
            'gross_margin' => 0.41,
            'cash_balance' => 18000,
            'debtor_days' => 42,
        ]);
        $this->financialSnapshot($client, now(), [
            'revenue' => 145000,
            'gross_margin' => 0.48,
            'cash_balance' => 32000,
            'debtor_days' => 31,
        ]);
        $this->businessValuation($client, 400000, now()->subMonths(9));
        $this->businessValuation($client, 560000, now());
        $this->analysisFixture($client);

        $report = app(ReportComposer::class)->compose($client, ReportType::Trajectory, $advisor);

        $this->assertSame(ReportType::Trajectory, $report->type);
        $this->assertSame('pending_review', $report->review_status);
        $this->assertTrue($report->sections->contains('key', 'financial_trends'));
        $this->assertTrue($report->sections->contains('key', 'pv_milestones'));
        $this->assertTrue($report->sections->contains('key', 'trajectory_narrative'));
        $this->assertStringContainsString('Revenue: 100,000 -> 145,000', $report->sections->firstWhere('key', 'financial_trends')->body);
        $this->assertStringContainsString('NZD 560,000 midpoint', $report->sections->firstWhere('key', 'pv_milestones')->body);
        $this->assertStringContainsString('requires advisor review', $report->sections->firstWhere('key', 'trajectory_narrative')->data_quality_note);
    }

    public function test_trajectory_report_can_be_marked_reviewed_by_advisor(): void
    {
        [$advisor, $client] = $this->clientWithTeam('trajectory-reviewer@example.test');
        $this->financialSnapshot($client, now()->subMonth(), ['revenue' => 80000]);
        $this->financialSnapshot($client, now(), ['revenue' => 95000]);
        $this->businessValuation($client, 300000, now()->subMonth());
        $this->businessValuation($client, 340000, now());
        $this->analysisFixture($client);

        $report = app(ReportComposer::class)->compose($client, ReportType::Trajectory, $advisor);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.reports.review', $report))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $this->assertTrue($report->refresh()->reviewed());
        $this->assertSame($advisor->getKey(), $report->reviewed_by_user_id);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'report.reviewed',
            'subject_id' => $report->id,
        ]);
    }

    public function test_advisor_route_generates_reports_and_portal_shows_client_reports_only(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithTeamAndClientUser();
        $this->businessValuation($client, 425000);
        $this->analysisFixture($client);
        $this->proposal($client);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.reports.store', $client), [
                'type' => ReportType::Client->value,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.reports.store', $client), [
                'type' => ReportType::Advisor->value,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.reports.store', $client), [
                'type' => ReportType::Stakeholder->value,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $this->assertDatabaseCount('reports', 3);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('client.report_store_url', route('advisor.clients.reports.store', $client, absolute: false))
                ->has('client.reports', 3));

        $this->actingAsMfa($clientUser)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('reports', 1)
                ->where('reports.0.title', ReportType::Client->label().' - '.$client->legal_name));
    }

    public function test_reports_and_sections_are_isolated_by_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Report RLS assertions require Postgres.');
        }

        $clientA = $this->client('Report A Limited');
        $clientB = $this->client('Report B Limited');
        $reportA = $this->storedReport($clientA);
        $reportB = $this->storedReport($clientB);

        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()]);

        $visibleReportIds = $this->withRlsRole(fn (): array => DB::table('reports')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());
        $visibleSectionReportIds = $this->withRlsRole(fn (): array => DB::table('report_sections')
            ->pluck('report_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $this->assertContains($reportA->id, $visibleReportIds);
        $this->assertNotContains($reportB->id, $visibleReportIds);
        $this->assertContains($reportA->id, $visibleSectionReportIds);
        $this->assertNotContains($reportB->id, $visibleSectionReportIds);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientWithTeam(string $advisorEmail = 'report-advisor@example.test'): array
    {
        [$advisor, $client] = $this->clientWithTeamAndClientUser($advisorEmail);

        return [$advisor, $client];
    }

    /**
     * @return array{0: User, 1: Client, 2: User}
     */
    private function clientWithTeamAndClientUser(string $advisorEmail = 'report-advisor@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $clientUser = User::factory()->withTwoFactor()->create([
            'email' => 'report-client-'.strtolower(fake()->bothify('????')).'@example.test',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);

        $client = $this->client('Report Client Limited', $advisor, $clientUser);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $clientUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client, $clientUser];
    }

    private function client(string $name, ?User $createdBy = null, ?User $primaryContact = null): Client
    {
        app(RequestContext::class)->apply('system', []);

        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $createdBy?->getKey(),
            'primary_contact_user_id' => $primaryContact?->getKey(),
        ]);
    }

    private function analysisFixture(Client $client): void
    {
        $run = AnalysisRun::query()->create([
            'client_id' => $client->id,
            'module' => AnalysisModule::Financial,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => AnalysisLens::values(),
            'data_quality_snapshot' => ['level' => Client::DATA_QUALITY_LOW],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->finding($client, $run, AnalysisLens::Descriptive, 'Revenue pattern', 'Revenue is concentrated in two service lines.');
        $this->finding($client, $run, AnalysisLens::Diagnostic, 'Margin issue', 'Gross margin pressure is linked to supplier increases.', AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED);
        $this->finding($client, $run, AnalysisLens::Predictive, 'Cash pressure', 'Working capital pressure is likely if debtor days stay elevated.');
        $this->finding($client, $run, AnalysisLens::Prescriptive, 'Recommendation roadmap', 'Prioritise pricing review and debtor follow-up before hiring.');
    }

    private function finding(
        Client $client,
        AnalysisRun $run,
        AnalysisLens $lens,
        string $title,
        string $body,
        string $documentSupport = AnalysisFinding::DOCUMENT_SUPPORT_NONE,
    ): AnalysisFinding {
        return AnalysisFinding::query()->create([
            'analysis_run_id' => $run->id,
            'client_id' => $client->id,
            'lens' => $lens,
            'severity' => FindingSeverity::Medium,
            'title' => $title,
            'body' => $body,
            'attributions' => [
                ['claim' => $title, 'source_reference' => 'test:'.$lens->value],
            ],
            'document_support' => $documentSupport,
            'uncertainty' => Uncertainty::Low,
            'data_quality_disclaimer' => 'Data quality note: fixture data quality is low.',
            'bias_signals' => [],
        ]);
    }

    private function businessValuation(Client $client, float $mid, mixed $asAt = null): BusinessValuation
    {
        $asAt ??= now();
        $calculation = PvCalculation::query()->create([
            'client_id' => $client->getKey(),
            'type' => PvType::BusinessValuation,
            'discount_method' => DiscountMethod::AdvisorConfigured,
            'discount_rate' => 0.12,
            'discount_rate_rationale' => 'Fixture valuation rate.',
            'inputs' => ['fixture' => true],
            'result' => ['present_value' => $mid],
            'as_at' => $asAt,
            'source_attributions' => [
                ['claim' => 'Fixture valuation', 'source_reference' => 'test:valuation'],
            ],
        ]);

        return BusinessValuation::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $calculation->getKey(),
            'sde_value' => ['mid' => $mid],
            'ebitda_value' => ['mid' => $mid],
            'dcf_value' => ['mid' => $mid],
            'reconciled_low' => $mid * 0.9,
            'reconciled_mid' => $mid,
            'reconciled_high' => $mid * 1.1,
            'adjustments' => [],
            'source_attributions' => [
                ['claim' => 'Fixture valuation', 'source_reference' => 'test:valuation'],
            ],
            'as_at' => $asAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function financialSnapshot(Client $client, mixed $periodEnd, array $metrics): FinancialSnapshot
    {
        $connection = AccountingConnection::query()->firstOrCreate([
            'client_id' => $client->id,
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'tenant-'.$client->id,
        ], [
            'status' => AccountingConnection::STATUS_CONNECTED,
            'token_envelope' => 'fixture',
            'token_envelope_meta' => ['fixture' => true],
            'scopes' => ['accounting.reports.read'],
            'connected_at' => now(),
        ]);

        return FinancialSnapshot::query()->create([
            'client_id' => $client->id,
            'accounting_connection_id' => $connection->id,
            'provider' => AccountingConnection::PROVIDER_XERO,
            'period_start' => $periodEnd->copy()->subMonth()->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'source' => 'fixture',
            'source_badge' => 'fixture',
            'degraded' => false,
            'profit_and_loss' => ['revenue' => $metrics['revenue'] ?? 0],
            'balance_sheet' => ['cash' => $metrics['cash_balance'] ?? 0],
            'cash_flow' => [],
            'metrics' => $metrics,
            'pulled_at' => now(),
        ]);
    }

    private function proposal(Client $client, float $mid = 12000, float $roi = 3.5): Proposal
    {
        $calculation = FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'method' => FeeMethod::OutcomeBased,
            'inputs' => ['fixture' => true],
            'suggested_low' => $mid * 0.8,
            'suggested_mid' => $mid,
            'suggested_high' => $mid * 1.2,
            'improvement_pv_total' => $mid * $roi,
            'risk_cost_pv_total' => 5000,
            'roi_ratio' => $roi,
            'justification' => ['fixture' => true],
        ]);

        return Proposal::query()->create([
            'client_id' => $client->id,
            'fee_calculation_id' => $calculation->id,
            'status' => ProposalStatus::Released,
            'version' => 1,
            'scope' => ['summary' => 'Report proposal fixture.'],
            'services' => [['name' => 'Advisor implementation support']],
            'pv_summary' => ['roi_ratio' => $roi],
            'roi_ratio' => $roi,
            'acceptance_terms' => ['phase' => 'phase_2_release_only'],
            'released_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    private function storedReport(Client $client): Report
    {
        $report = Report::query()->create([
            'client_id' => $client->id,
            'type' => ReportType::Client,
            'title' => 'Stored report',
            'generated_at' => now(),
            'metadata' => [],
        ]);

        ReportSection::query()->create([
            'report_id' => $report->id,
            'client_id' => $client->id,
            'key' => 'stored',
            'title' => 'Stored section',
            'body' => 'Stored body',
            'position' => 1,
            'attributions' => [['claim' => 'Stored', 'source_reference' => 'test:stored']],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_NONE,
            'document_support_note' => 'Document support: none.',
            'data_quality_note' => 'Data quality note: stored.',
        ]);

        return $report;
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
            GRANT SELECT ON reports, report_sections TO %1$s;
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
            DB::statement('SAVEPOINT reports_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT reports_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT reports_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
