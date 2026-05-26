<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\GovernanceReviewFinding;
use App\Models\NpoEngagement;
use App\Models\Report;
use App\Models\User;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pptx\Contracts\PptxGenerator;
use App\Services\Reports\ReportComposer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class GovernanceReviewReportTest extends TestCase
{
    use RefreshDatabase;

    private object $renderer;

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

        $this->app->instance(PptxGenerator::class, new class implements PptxGenerator
        {
            public function render(Report $report): string
            {
                return 'PPTX '.$report->title;
            }
        });
    }

    public function test_governance_review_report_renders_mandatory_sections_and_stamps_engagement(): void
    {
        [$advisor, $client, $engagement] = $this->npoClient();
        $document = $this->verifiedDocument($client, $advisor);
        $this->reviewedFindings($engagement, $advisor, $document->id);

        $report = app(ReportComposer::class)->composeGovernanceReview($engagement, $advisor);

        $this->assertSame(ReportType::GovernanceReview, $report->type);
        $this->assertSame($engagement->id, $report->npo_engagement_id);
        $this->assertSame('not_required', $report->review_status);
        $this->assertNotNull($report->pdf_path);
        Storage::disk('secure_local')->assertExists($report->pdf_path);

        $sectionKeys = $report->sections->pluck('key')->all();
        $this->assertContains('s42g_evidence_statement', $sectionKeys);
        $this->assertContains('legal_disclaimer', $sectionKeys);
        $this->assertContains('twelve_month_action_plan', $sectionKeys);
        $this->assertContains('board_composition_skills', $sectionKeys);
        $this->assertContains('constitution_currency', $sectionKeys);
        $this->assertContains('conflicts_of_interest', $sectionKeys);
        $this->assertContains('financial_oversight', $sectionKeys);
        $this->assertContains('compliance_status', $sectionKeys);

        $this->assertStringContainsString('s.42G evidence statement', $this->renderer->html);
        $this->assertStringContainsString('not legal advice', $this->renderer->html);
        $this->assertStringContainsString('Advisor-reviewed findings', $this->renderer->html);
        $this->assertStringContainsString('Document support: verified document evidence is linked.', $this->renderer->html);

        $report->sections->each(function ($section): void {
            $this->assertNotSame([], $section->attributions);
            $this->assertStringContainsString('Data quality note:', $section->data_quality_note);
            $this->assertNotSame('', $section->document_support_note);
        });
        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo.governance_review_report_generated',
            'subject_id' => $report->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_advisor_route_can_generate_and_download_governance_review_report(): void
    {
        [$advisor, $client, $engagement] = $this->npoClient('route-governance-advisor@example.test');
        $document = $this->verifiedDocument($client, $advisor);
        $this->reviewedFindings($engagement, $advisor, $document->id);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.reports.store', $client), [
                'type' => ReportType::GovernanceReview->value,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $report = Report::query()
            ->where('client_id', $client->id)
            ->where('type', ReportType::GovernanceReview)
            ->firstOrFail();

        $this->assertSame($engagement->id, $report->npo_engagement_id);

        $response = $this->actingAsMfa($advisor)
            ->get(route('advisor.reports.download', $report));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_governance_review_report_requires_advisor_reviewed_findings(): void
    {
        [$advisor, , $engagement] = $this->npoClient('pending-governance-advisor@example.test');
        GovernanceReviewFinding::query()->create([
            'client_id' => $engagement->client_id,
            'npo_engagement_id' => $engagement->id,
            'finding_key' => 'board_composition',
            'category' => 'governance_capability',
            'severity' => FindingSeverity::High,
            'title' => 'Pending board finding',
            'body' => 'This finding is not reviewed yet.',
            'criteria' => [['key' => 'board_composition']],
            'evidence' => [],
            'attributions' => [['claim' => 'Pending', 'source_reference' => 'test:pending']],
            'uncertainty' => Uncertainty::High,
            'status' => GovernanceReviewFinding::STATUS_PENDING_ADVISOR_REVIEW,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires advisor-reviewed governance findings');

        app(ReportComposer::class)->composeGovernanceReview($engagement, $advisor);
    }

    /**
     * @return array{0: User, 1: Client, 2: NpoEngagement}
     */
    private function npoClient(string $advisorEmail = 'governance-report-advisor@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Community Governance Trust',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::NPO->value],
        ]);

        $engagement = NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => NpoEngagementSubType::GovernanceReview,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
            'isa_2022_reregistered' => null,
        ]);

        return [$advisor, $client, $engagement];
    }

    private function verifiedDocument(Client $client, User $advisor): Document
    {
        $document = Document::query()->create([
            'client_id' => $client->id,
            'category' => Document::CATEGORY_COMPLIANCE_DOC,
            'original_filename' => 'constitution.pdf',
            'stored_path' => 'reports-test/'.Str::uuid().'.pdf',
            'byte_size' => 2048,
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', (string) Str::uuid()),
            'uploaded_by_user_id' => $advisor->getKey(),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);

        DocumentVerification::query()->create([
            'document_id' => $document->id,
            'client_id' => $client->id,
            'claim_source' => 'npo_governance_report_test',
            'context_hash' => hash('sha256', $document->id.'governance-report'),
            'claim_text' => 'The constitution supports the governance review evidence.',
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
            'confidence' => 0.91,
            'verified_at' => now(),
        ]);

        return $document;
    }

    private function reviewedFindings(NpoEngagement $engagement, User $advisor, string $documentId): void
    {
        $this->reviewedFinding($engagement, $advisor, 'legal_structure_compliance', 'legal_structure', FindingSeverity::Info, 'Legal criteria selected', 'Registered charity criteria include Charities Act 2005 s.42G and Charities Amendment Act 2023.', $documentId);
        $this->reviewedFinding($engagement, $advisor, 'board_composition', 'governance_capability', FindingSeverity::Medium, 'Board composition and skills gap', 'Board roles are mostly filled, with succession planning to strengthen over the next 12 months.', $documentId);
        $this->reviewedFinding($engagement, $advisor, 'constitution_currency', 'legal_compliance', FindingSeverity::High, 'Constitution and statutory currency', 'The constitution should be checked against current charity governance practice and s.42G officer eligibility.', $documentId);
        $this->reviewedFinding($engagement, $advisor, 'conflicts_of_interest', 'risk_controls', FindingSeverity::Medium, 'Conflicts of interest framework', 'The COI register is present and declaration cadence should be confirmed in minutes.', $documentId);
        $this->reviewedFinding($engagement, $advisor, 'financial_oversight', 'financial_governance', FindingSeverity::Low, 'Financial oversight controls', 'Financial statements and reporting cadence are available for board review.', $documentId);
    }

    private function reviewedFinding(
        NpoEngagement $engagement,
        User $advisor,
        string $key,
        string $category,
        FindingSeverity $severity,
        string $title,
        string $body,
        string $documentId,
    ): GovernanceReviewFinding {
        return GovernanceReviewFinding::query()->create([
            'client_id' => $engagement->client_id,
            'npo_engagement_id' => $engagement->id,
            'finding_key' => $key,
            'category' => $category,
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'criteria' => [['key' => $key, 'label' => $title]],
            'evidence' => [[
                'prompt' => $title,
                'value' => 'Reviewed evidence supplied.',
                'attached_document_ids' => [$documentId],
                'source_reference' => 'document:'.$documentId,
            ]],
            'attributions' => [
                ['claim' => $title, 'source_reference' => 'document:'.$documentId],
                ['claim' => 'NPO engagement', 'source_reference' => 'npo_engagement:'.$engagement->id],
            ],
            'uncertainty' => Uncertainty::Medium,
            'status' => GovernanceReviewFinding::STATUS_REVIEWED,
            'advisor_notes' => 'Reviewed for report generation.',
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $advisor->getKey(),
        ]);
    }
}
