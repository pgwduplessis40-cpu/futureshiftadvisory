<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\EngagementType;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Analysis\Modules\WebsiteAudit;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WebsiteAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_website_audit_runs_on_the_analysis_spine_with_cited_findings(): void
    {
        $client = $this->clientWithWebsiteEvidence();

        $run = app(AnalysisRunner::class)->run($client, app(WebsiteAudit::class));

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('website_audit', $run->module->value);
        $this->assertSame(AnalysisLens::values(), $run->framework_lenses);
        $this->assertCount(4, $run->findings);

        $diagnostic = $run->findings->firstWhere('lens', AnalysisLens::Diagnostic);
        $this->assertInstanceOf(AnalysisFinding::class, $diagnostic);
        $this->assertStringContainsString('Product/service evidence is present', $diagnostic->body);
        $this->assertStringContainsString('actual offers', $diagnostic->body);
        $this->assertStringContainsString('mobile performance', $diagnostic->body);
        $this->assertStringContainsString('CTA clarity', $diagnostic->body);
        $this->assertStringContainsString('SEO, GEO, AEO, and AIO', $diagnostic->body);

        $predictive = $run->findings->firstWhere('lens', AnalysisLens::Predictive);
        $this->assertInstanceOf(AnalysisFinding::class, $predictive);
        $this->assertStringContainsString('SEO, GEO, AEO, and AIO', $predictive->body);

        $this->assertTrue(collect($diagnostic->attributions)->contains(
            fn (array $attribution): bool => str_starts_with($attribution['source_reference'], 'questionnaire_answer:'),
        ));
        $this->assertSame(AnalysisFinding::DOCUMENT_SUPPORT_NONE, $diagnostic->document_support);
    }

    public function test_website_audit_respects_document_verification_gate(): void
    {
        $client = $this->clientWithWebsiteEvidence();
        $this->blockingVerificationFor($client);

        $run = app(AnalysisRunner::class)->run($client, app(WebsiteAudit::class));

        $this->assertSame(AnalysisRun::STATUS_BLOCKED_DOCUMENTS, $run->status);
        $this->assertSame(0, $run->findings()->count());
    }

    public function test_positive_website_wording_is_not_treated_as_a_gap(): void
    {
        $client = $this->clientWithWebsiteEvidence([
            'website' => 'https://example.co.nz service pages clearly explain cash-flow advisory, CFO support, and pricing workshops for New Zealand SMEs.',
            'discoverability' => 'SEO metadata, schema, FAQ answer blocks, GEO citations, AEO answers, and AIO-friendly service summaries are implemented.',
            'mobile' => 'Mobile pages are fast and responsive for enquiry traffic.',
            'cta' => 'The enquiry CTA is clear and visible above the fold on each service page.',
        ]);

        $run = app(AnalysisRunner::class)->run($client, app(WebsiteAudit::class));

        $diagnostic = $run->findings->firstWhere('lens', AnalysisLens::Diagnostic);
        $this->assertInstanceOf(AnalysisFinding::class, $diagnostic);
        $this->assertStringContainsString('mobile speed or responsiveness positively', $diagnostic->body);
        $this->assertStringContainsString('CTA visibility positively', $diagnostic->body);
        $this->assertStringContainsString('supplied evidence mentions search', $diagnostic->body);
        $this->assertStringNotContainsString('flags mobile performance', $diagnostic->body);
        $this->assertStringNotContainsString('flags enquiry or CTA clarity', $diagnostic->body);
        $this->assertStringNotContainsString('flags missing or weak metadata', $diagnostic->body);
    }

    public function test_website_audit_uses_only_standard_advisory_questionnaire_responses(): void
    {
        $client = $this->clientWithWebsiteEvidence([
            'website' => 'https://example.co.nz service pages clearly explain cash-flow advisory and CFO support.',
            'discoverability' => 'SEO metadata and schema are present.',
            'mobile' => 'Mobile pages are fast and responsive.',
            'cta' => 'The enquiry CTA is clear.',
        ]);
        $user = User::factory()->create();
        [$questionnaire, $questions] = $this->questionnaireWithQuestions(QuestionnaireSet::DUE_DILIGENCE);
        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now()->addMinute(),
            'submitted_by_user_id' => $user->getKey(),
        ]);
        $nonStandardAnswer = $response->answers()->create([
            'question_id' => $questions['website']->id,
            'value' => 'https://irrelevant.example has slow mobile pages, no schema, and a hidden CTA.',
            'attached_document_ids' => [],
        ]);
        foreach (['products', 'discoverability', 'mobile', 'cta'] as $key) {
            $response->answers()->create([
                'question_id' => $questions[$key]->id,
                'value' => 'Non-Standard Advisory evidence should not drive the website audit.',
                'attached_document_ids' => [],
            ]);
        }

        $run = app(AnalysisRunner::class)->run($client, app(WebsiteAudit::class));

        $diagnostic = $run->findings->firstWhere('lens', AnalysisLens::Diagnostic);
        $this->assertInstanceOf(AnalysisFinding::class, $diagnostic);
        $this->assertStringNotContainsString('flags mobile performance', $diagnostic->body);
        $this->assertFalse(collect($diagnostic->attributions)->contains(
            fn (array $attribution): bool => $attribution['source_reference'] === "questionnaire_answer:{$nonStandardAnswer->id}",
        ));
    }

    private function clientWithWebsiteEvidence(array $overrides = []): Client
    {
        $user = User::factory()->create();
        $values = [
            'website' => 'https://example.co.nz has useful service pages for virtual CFO and cash-flow advisory but weak local SEO for NZ advisory searches.',
            'products' => 'The client sells fixed-fee cash-flow advisory, monthly CFO support, and pricing workshops for New Zealand SMEs.',
            'discoverability' => 'The site has no schema, FAQ answer blocks, AEO content, GEO citations, or AIO-friendly service summaries.',
            'mobile' => 'Mobile pages are slow and not responsive enough for enquiry traffic.',
            'cta' => 'The main enquiry CTA is unclear and sits below most service-page content.',
            ...$overrides,
        ];

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Website Audit Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $user->getKey(),
        ]);

        [$questionnaire, $questions] = $this->questionnaireWithQuestions();

        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->getKey(),
        ]);

        $response->answers()->create([
            'question_id' => $questions['website']->id,
            'value' => $values['website'],
            'attached_document_ids' => [],
        ]);
        $response->answers()->create([
            'question_id' => $questions['products']->id,
            'value' => $values['products'],
            'attached_document_ids' => [],
        ]);
        $response->answers()->create([
            'question_id' => $questions['discoverability']->id,
            'value' => $values['discoverability'],
            'attached_document_ids' => [],
        ]);
        $response->answers()->create([
            'question_id' => $questions['mobile']->id,
            'value' => $values['mobile'],
            'attached_document_ids' => [],
        ]);
        $response->answers()->create([
            'question_id' => $questions['cta']->id,
            'value' => $values['cta'],
            'attached_document_ids' => [],
        ]);

        return $client;
    }

    /**
     * @return array{0: Questionnaire, 1: array{website: QuestionnaireQuestion, products: QuestionnaireQuestion, discoverability: QuestionnaireQuestion, mobile: QuestionnaireQuestion, cta: QuestionnaireQuestion}}
     */
    private function questionnaireWithQuestions(QuestionnaireSet $set = QuestionnaireSet::STANDARD_ADVISORY): array
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => $set,
            'version' => 'wo45-'.Str::lower(Str::random(8)),
            'title' => 'WO-45 Website Audit Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Website evidence',
        ]);

        $questions = [
            'website' => $section->questions()->create([
                'order' => 1,
                'type' => QuestionnaireQuestionType::TEXT,
                'prompt' => 'What website, SEO, or local search information is available?',
                'required' => true,
            ]),
            'products' => $section->questions()->create([
                'order' => 2,
                'type' => QuestionnaireQuestionType::TEXT,
                'prompt' => 'What products or services does the business sell?',
                'required' => true,
            ]),
            'discoverability' => $section->questions()->create([
                'order' => 3,
                'type' => QuestionnaireQuestionType::TEXT,
                'prompt' => 'What SEO, GEO, AEO, or AIO discoverability issues are known?',
                'required' => true,
            ]),
            'mobile' => $section->questions()->create([
                'order' => 4,
                'type' => QuestionnaireQuestionType::TEXT,
                'prompt' => 'What mobile performance or UX issues are known?',
                'required' => true,
            ]),
            'cta' => $section->questions()->create([
                'order' => 5,
                'type' => QuestionnaireQuestionType::TEXT,
                'prompt' => 'What CTA or enquiry conversion issues are known?',
                'required' => true,
            ]),
        ];

        return [$questionnaire, $questions];
    }

    private function blockingVerificationFor(Client $client): void
    {
        $document = Document::query()->create([
            'client_id' => $client->id,
            'category' => Document::CATEGORY_OTHER,
            'original_filename' => 'website-claim.txt',
            'stored_path' => 'website-audit/'.Str::uuid().'.txt',
            'byte_size' => 10,
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', $client->id),
            'uploaded_by_user_id' => $client->primary_contact_user_id,
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);

        DocumentVerification::query()->create([
            'document_id' => $document->id,
            'client_id' => $client->id,
            'claim_source' => 'website-audit',
            'context_hash' => hash('sha256', $document->id),
            'claim_text' => 'Website evidence needs advisor review.',
            'outcome' => DocumentVerification::OUTCOME_ADVISORY_FLAG,
            'verified_at' => now(),
        ]);
    }
}
