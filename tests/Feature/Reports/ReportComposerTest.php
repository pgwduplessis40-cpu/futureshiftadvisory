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
use App\Models\Template;
use App\Models\User;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pptx\Contracts\PptxGenerator;
use App\Services\Reports\ReportComposer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

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
                DB::statement('REVOKE SELECT ON reports, report_sections, entrepreneur_profiles FROM '.self::RLS_APP_ROLE);
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
        $this->assertSame('pending_review', $report->review_status);
        $this->assertTrue($report->metadata['client_release_gate']);
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

    public function test_client_report_uses_active_report_template(): void
    {
        [$advisor, $client] = $this->clientWithTeam('template-report-advisor@example.test');
        $this->businessValuation($client, 500000);
        $this->analysisFixture($client);

        Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Advisor Report Template',
            'body' => 'Advisor-only template body.',
            'structure' => ['report_type' => ReportType::Advisor->value],
            'status' => Template::STATUS_ACTIVE,
            'version' => 1,
        ]);

        /** @var Template $template */
        $template = Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Client Report Template',
            'body' => <<<'HTML'
<main data-report-template="client-template">
<header class="client-template-header">
<h1>{{ report_title }}</h1>
<p>{{ client_name }}</p>
<p>{{ template_title }} v{{ template_version }}</p>
</header>
{{ sections }}
</main>
HTML,
            'structure' => [
                'report_type' => ReportType::Client->value,
                'sections' => [
                    [
                        'position' => 1,
                        'heading' => 'Client-facing opening',
                        'purpose' => 'Frame the advisory report for client review.',
                    ],
                ],
            ],
            'status' => Template::STATUS_ACTIVE,
            'version' => 3,
        ]);

        $report = app(ReportComposer::class)->compose($client, ReportType::Client, $advisor);

        $this->assertSame($template->id, data_get($report->metadata, 'template.id'));
        $this->assertSame(3, data_get($report->metadata, 'template.version'));
        $this->assertStringContainsString('data-report-template="client-template"', $this->renderer->html);
        $this->assertStringContainsString('Client Report Template v3', $this->renderer->html);
        $this->assertStringContainsString('Current valuation range', $this->renderer->html);
        $this->assertStringNotContainsString('Advisor-only template body', $this->renderer->html);
    }

    public function test_newest_active_report_template_is_used_instead_of_old_or_archived_templates(): void
    {
        [$advisor, $client] = $this->clientWithTeam('current-template-advisor@example.test');
        $this->businessValuation($client, 500000);
        $this->analysisFixture($client);

        Carbon::setTestNow('2026-06-18 09:00:00');
        Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Report Template VS 1',
            'body' => '<main data-report-template="old-active">{{ sections }}</main>',
            'structure' => ['report_type' => ReportType::Client->value],
            'status' => Template::STATUS_ACTIVE,
            'version' => 1,
        ]);

        Carbon::setTestNow('2026-06-18 10:00:00');
        Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Archived Report Template VS 2',
            'body' => '<main data-report-template="archived">{{ sections }}</main>',
            'structure' => ['report_type' => ReportType::Client->value],
            'status' => Template::STATUS_ARCHIVED,
            'version' => 2,
        ]);

        Carbon::setTestNow('2026-06-19 09:00:00');
        /** @var Template $template */
        $template = Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Report Template VS 3',
            'body' => '',
            'structure' => [
                'sections' => [],
                'source_kind' => 'uploaded_file',
                'uploaded_file' => [
                    'original_name' => 'Report_Template_VS_3.docx',
                ],
            ],
            'status' => Template::STATUS_ACTIVE,
            'version' => 3,
        ]);

        $report = app(ReportComposer::class)->compose($client, ReportType::Client, $advisor);

        $this->assertSame($template->id, data_get($report->metadata, 'template.id'));
        $this->assertSame(3, data_get($report->metadata, 'template.version'));
        $this->assertSame('Report_Template_VS_3.docx', data_get($report->metadata, 'template.uploaded_file'));
        $this->assertStringContainsString('Report Template VS 3 v3', $this->renderer->html);
        $this->assertStringNotContainsString('data-report-template="old-active"', $this->renderer->html);
        $this->assertStringNotContainsString('data-report-template="archived"', $this->renderer->html);

        Carbon::setTestNow();
    }

    public function test_uploaded_active_report_template_beats_live_synced_placeholder_updated_later(): void
    {
        [$advisor, $client] = $this->clientWithTeam('uploaded-template-advisor@example.test');
        $this->businessValuation($client, 500000);
        $this->analysisFixture($client);

        Carbon::setTestNow('2026-06-19 09:00:00');
        /** @var Template $template */
        $template = Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Report Template VS1',
            'body' => '',
            'structure' => [
                'source_kind' => 'uploaded_file',
                'uploaded_file' => [
                    'original_name' => 'FSA_Report_Template.docx',
                ],
            ],
            'status' => Template::STATUS_ACTIVE,
            'version' => 1,
        ]);

        Carbon::setTestNow('2026-06-19 10:00:00');
        Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'FSA_Report V1',
            'body' => '<main data-report-template="live-synced-placeholder">{{ sections }}</main>',
            'structure' => ['report_type' => ReportType::Client->value],
            'status' => Template::STATUS_ACTIVE,
            'version' => 1,
        ]);

        $report = app(ReportComposer::class)->compose($client, ReportType::Client, $advisor);

        $this->assertSame($template->id, data_get($report->metadata, 'template.id'));
        $this->assertSame('FSA_Report_Template.docx', data_get($report->metadata, 'template.uploaded_file'));
        $this->assertStringContainsString('Report Template VS1 v1', $this->renderer->html);
        $this->assertStringNotContainsString('data-report-template="live-synced-placeholder"', $this->renderer->html);

        Carbon::setTestNow();
    }

    public function test_uploaded_docx_report_template_renders_file_body_for_client_report_review(): void
    {
        [$advisor, $client] = $this->clientWithTeam('uploaded-docx-template-advisor@example.test');
        $this->businessValuation($client, 500000);
        $this->analysisFixture($client);

        $path = 'documents/template_file/report-template.docx';
        Storage::disk('secure_local')->put($path, $this->minimalDocx([
            ['FSA uploaded DOCX report shell', 'Title'],
            ['{{ report_title }}'],
            ['Prepared for {{ client_name }}'],
            ['{{ sections }}'],
        ]));

        /** @var Template $template */
        $template = Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Uploaded FSA Client Report',
            'body' => '',
            'structure' => [
                'source_kind' => 'uploaded_file',
                'uploaded_file' => [
                    'stored_path' => $path,
                    'original_name' => 'Uploaded_FSA_Client_Report.docx',
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'extension' => 'docx',
                    'sha256' => hash('sha256', Storage::disk('secure_local')->get($path)),
                ],
            ],
            'status' => Template::STATUS_ACTIVE,
            'version' => 1,
        ]);

        $report = app(ReportComposer::class)->compose($client, ReportType::Client, $advisor);

        $this->assertSame($template->id, data_get($report->metadata, 'template.id'));
        $this->assertSame('uploaded_docx_html_v1', data_get($report->metadata, 'template.render_strategy'));
        $this->assertSame('Uploaded_FSA_Client_Report.docx', data_get($report->metadata, 'template.uploaded_file'));
        $this->assertStringContainsString('data-report-template-source="uploaded-docx"', $this->renderer->html);
        $this->assertStringContainsString('FSA uploaded DOCX report shell', $this->renderer->html);
        $this->assertStringContainsString('Client Report - Report Client Limited', $this->renderer->html);
        $this->assertStringContainsString('Prepared for Report Client Limited', $this->renderer->html);
        $this->assertStringContainsString('Current valuation range', $this->renderer->html);
        $this->assertStringNotContainsString('class="report-cover"', $this->renderer->html);
    }

    public function test_advisor_report_download_rerenders_existing_uploaded_docx_pdf_without_renderer_marker(): void
    {
        [$advisor, $client] = $this->clientWithTeam('uploaded-docx-existing-report-advisor@example.test');
        $this->businessValuation($client, 500000);
        $this->analysisFixture($client);

        $path = 'documents/template_file/current-report-template.docx';
        Storage::disk('secure_local')->put($path, $this->minimalDocx([
            ['Current uploaded DOCX report shell', 'Title'],
            ['{{ report_title }}'],
            ['{{ sections }}'],
        ]));

        Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Current Uploaded FSA Client Report',
            'body' => '',
            'structure' => [
                'source_kind' => 'uploaded_file',
                'uploaded_file' => [
                    'stored_path' => $path,
                    'original_name' => 'Current_Uploaded_FSA_Client_Report.docx',
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'extension' => 'docx',
                    'sha256' => hash('sha256', Storage::disk('secure_local')->get($path)),
                ],
            ],
            'status' => Template::STATUS_ACTIVE,
            'version' => 1,
        ]);

        $report = app(ReportComposer::class)->compose($client, ReportType::Client, $advisor);
        $oldPdfPath = $report->pdf_path;
        $metadata = $report->metadata;
        unset($metadata['template']['render_strategy']);
        $report->forceFill(['metadata' => $metadata])->save();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.reports.download', $report))
            ->assertOk();

        $report->refresh();

        $this->assertNotSame($oldPdfPath, $report->pdf_path);
        $this->assertSame('uploaded_docx_html_v1', data_get($report->metadata, 'template.render_strategy'));
        $this->assertStringContainsString('Current uploaded DOCX report shell', $this->renderer->html);
        $this->assertStringContainsString('data-report-template-source="uploaded-docx"', $this->renderer->html);
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

    public function test_advisor_can_edit_report_sections_with_revision_history_comments_and_reopened_review(): void
    {
        [$advisor, $client] = $this->clientWithTeam('report-section-editor@example.test');
        $this->businessValuation($client, 420000);
        $this->analysisFixture($client);
        $this->proposal($client);

        $report = app(ReportComposer::class)->compose($client, ReportType::Client, $advisor);
        $section = $report->sections->firstOrFail();
        $oldPdfPath = $report->pdf_path;

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.reports.review', $report))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $this->assertTrue($report->refresh()->reviewed());

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.reports.sections.update', [$report, $section]), [
                'body' => 'Advisor-edited section body with source-checked wording.',
                'reason' => 'Aligned language with verified evidence.',
            ])
            ->assertRedirect();

        $this->assertSame('Advisor-edited section body with source-checked wording.', $section->refresh()->body);
        $this->assertSame('pending_review', $report->refresh()->review_status);
        $this->assertNull($report->reviewed_at);
        $this->assertNotSame($oldPdfPath, $report->pdf_path);
        Storage::disk('secure_local')->assertExists($report->pdf_path);
        $this->assertDatabaseHas('report_section_revisions', [
            'report_id' => $report->id,
            'report_section_id' => $section->id,
            'revision_number' => 1,
            'body_after' => 'Advisor-edited section body with source-checked wording.',
            'reason' => 'Aligned language with verified evidence.',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'report.section_edited',
            'subject_id' => $section->id,
        ]);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.reports.sections.comments.store', [$report, $section]), [
                'body' => 'Check this section with the client before release.',
                'visibility' => 'advisor_only',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('report_section_comments', [
            'report_id' => $report->id,
            'report_section_id' => $section->id,
            'body' => 'Check this section with the client before release.',
            'visibility' => 'advisor_only',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'report.section_commented',
        ]);
    }

    public function test_advisor_can_download_generated_report_pdf(): void
    {
        [$advisor, $client] = $this->clientWithTeam('report-download@example.test');
        $this->businessValuation($client, 480000);
        $this->analysisFixture($client);
        $this->proposal($client);

        $report = app(ReportComposer::class)->compose($client, ReportType::Client, $advisor);
        $this->assertNotNull($report->pdf_path);

        $response = $this->actingAsMfa($advisor)
            ->get(route('advisor.reports.download', $report));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('inline;', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->assertDatabaseHas('audit_events', [
            'action' => 'report.downloaded',
            'subject_id' => $report->id,
        ]);
    }

    public function test_advisor_report_download_rerenders_when_pdf_missing(): void
    {
        [$advisor, $client] = $this->clientWithTeam('report-missing@example.test');
        $report = $this->storedReport($client); // no pdf_path set

        $response = $this->actingAsMfa($advisor)
            ->get(route('advisor.reports.download', $report));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertNotNull($report->refresh()->pdf_path);
        Storage::disk('secure_local')->assertExists($report->pdf_path);
    }

    public function test_advisor_report_download_rerenders_when_template_is_historic(): void
    {
        [$advisor, $client] = $this->clientWithTeam('report-historic-template@example.test');
        $this->businessValuation($client, 480000);
        $this->analysisFixture($client);
        $this->proposal($client);

        Carbon::setTestNow('2026-06-18 09:00:00');
        /** @var Template $historicTemplate */
        $historicTemplate = Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Historic Report Template',
            'body' => '',
            'structure' => [
                'source_kind' => 'uploaded_file',
                'uploaded_file' => [
                    'original_name' => 'Historic_Report_Template.docx',
                ],
            ],
            'status' => Template::STATUS_ACTIVE,
            'version' => 1,
        ]);

        $report = app(ReportComposer::class)->compose($client, ReportType::Client, $advisor);
        $oldPdfPath = $report->pdf_path;
        $this->assertSame($historicTemplate->id, data_get($report->metadata, 'template.id'));

        Carbon::setTestNow('2026-06-19 09:00:00');
        $historicTemplate->forceFill(['status' => Template::STATUS_ARCHIVED])->save();
        /** @var Template $currentTemplate */
        $currentTemplate = Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Current Report Template',
            'body' => '',
            'structure' => [
                'source_kind' => 'uploaded_file',
                'uploaded_file' => [
                    'original_name' => 'Current_Report_Template.docx',
                ],
            ],
            'status' => Template::STATUS_ACTIVE,
            'version' => 2,
        ]);

        $response = $this->actingAsMfa($advisor)
            ->get(route('advisor.reports.download', $report));

        $response->assertOk();
        $report->refresh();

        $this->assertNotSame($oldPdfPath, $report->pdf_path);
        $this->assertSame($currentTemplate->id, data_get($report->metadata, 'template.id'));
        $this->assertSame(2, data_get($report->metadata, 'template.version'));
        $this->assertStringContainsString('Current Report Template v2', $response->getContent());
        Storage::disk('secure_local')->assertExists($report->pdf_path);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'report.rerendered',
            'subject_id' => $report->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_advisor_report_download_prefers_newer_active_template_over_older_higher_version(): void
    {
        [$advisor, $client] = $this->clientWithTeam('report-active-template-version@example.test');
        $this->businessValuation($client, 480000);
        $this->analysisFixture($client);
        $this->proposal($client);

        Carbon::setTestNow('2026-06-18 09:00:00');
        /** @var Template $lowerTemplate */
        $lowerTemplate = Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Report Template VS 1',
            'body' => '<main data-report-template="lower-version"><p>{{ template_title }} v{{ template_version }}</p>{{ sections }}</main>',
            'structure' => ['report_type' => ReportType::Client->value],
            'status' => Template::STATUS_ACTIVE,
            'version' => 5,
        ]);

        $report = app(ReportComposer::class)->compose($client, ReportType::Client, $advisor);
        $oldPdfPath = $report->pdf_path;
        $this->assertSame($lowerTemplate->id, data_get($report->metadata, 'template.id'));

        Carbon::setTestNow('2026-06-19 09:00:00');
        /** @var Template $higherTemplate */
        $higherTemplate = Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Report Template VS 2',
            'body' => '<main data-report-template="higher-version"><p>{{ template_title }} v{{ template_version }}</p>{{ sections }}</main>',
            'structure' => ['report_type' => ReportType::Client->value],
            'status' => Template::STATUS_ACTIVE,
            'version' => 1,
        ]);

        $response = $this->actingAsMfa($advisor)
            ->get(route('advisor.reports.download', $report));

        $response->assertOk();
        $report->refresh();

        $this->assertNotSame($oldPdfPath, $report->pdf_path);
        $this->assertSame($higherTemplate->id, data_get($report->metadata, 'template.id'));
        $this->assertSame(1, data_get($report->metadata, 'template.version'));
        $this->assertStringContainsString('Report Template VS 2 v1', $response->getContent());
        $this->assertStringContainsString('data-report-template="higher-version"', $this->renderer->html);
        $this->assertStringNotContainsString('data-report-template="lower-version"', $this->renderer->html);

        Carbon::setTestNow();
    }

    public function test_report_review_refreshes_stale_template_instead_of_releasing(): void
    {
        [$advisor, $client] = $this->clientWithTeam('report-review-template-refresh@example.test');
        $this->businessValuation($client, 480000);
        $this->analysisFixture($client);
        $this->proposal($client);

        Carbon::setTestNow('2026-06-18 09:00:00');
        Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Report Template VS 1',
            'body' => '<main data-report-template="release-lower-version">{{ sections }}</main>',
            'structure' => ['report_type' => ReportType::Client->value],
            'status' => Template::STATUS_ACTIVE,
            'version' => 1,
        ]);

        $report = app(ReportComposer::class)->compose($client, ReportType::Client, $advisor);
        $oldPdfPath = $report->pdf_path;

        Carbon::setTestNow('2026-06-19 09:00:00');
        /** @var Template $higherTemplate */
        $higherTemplate = Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Report Template VS 2',
            'body' => '<main data-report-template="release-higher-version">{{ sections }}</main>',
            'structure' => ['report_type' => ReportType::Client->value],
            'status' => Template::STATUS_ACTIVE,
            'version' => 2,
        ]);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.reports.review', $report))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHas('status', 'report-template-refreshed');

        $report->refresh();

        $this->assertSame('pending_review', $report->review_status);
        $this->assertNull($report->reviewed_at);
        $this->assertNotSame($oldPdfPath, $report->pdf_path);
        $this->assertSame($higherTemplate->id, data_get($report->metadata, 'template.id'));
        $this->assertSame(2, data_get($report->metadata, 'template.version'));

        Carbon::setTestNow();
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
        $clientReport = Report::query()
            ->where('client_id', $client->id)
            ->where('type', ReportType::Client->value)
            ->firstOrFail();

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
                ->has('reports', 0));

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.reports.review', $clientReport))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

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
     * @param  array<int, array{0:string,1?:string}>  $paragraphs
     */
    private function minimalDocx(array $paragraphs): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fsa-test-docx-');
        $this->assertIsString($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML);
        $zip->addFromString('word/document.xml', sprintf(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    %s
    <w:sectPr/>
  </w:body>
</w:document>
XML,
            implode("\n", array_map(
                fn (array $paragraph): string => $this->wordParagraph($paragraph[0], $paragraph[1] ?? null),
                $paragraphs,
            )),
        ));
        $zip->close();

        $bytes = file_get_contents($path);
        @unlink($path);

        $this->assertIsString($bytes);

        return $bytes;
    }

    private function wordParagraph(string $text, ?string $style = null): string
    {
        $styleXml = $style === null
            ? ''
            : sprintf('<w:pPr><w:pStyle w:val="%s"/></w:pPr>', htmlspecialchars($style, ENT_XML1 | ENT_QUOTES, 'UTF-8'));

        return sprintf(
            '<w:p>%s<w:r><w:t>%s</w:t></w:r></w:p>',
            $styleXml,
            htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
        );
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
            GRANT SELECT ON reports, report_sections, entrepreneur_profiles TO %1$s;
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
