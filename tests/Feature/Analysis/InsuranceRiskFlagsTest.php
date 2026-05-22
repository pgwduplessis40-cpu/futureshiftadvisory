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
use App\Services\Analysis\Modules\InsuranceRiskFlags;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InsuranceRiskFlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_insurance_risk_module_flags_gaps_and_verified_certificate_evidence(): void
    {
        $client = $this->clientWithInsuranceEvidence();
        $certificate = $this->verifiedInsuranceCertificate($client);

        $run = app(AnalysisRunner::class)->run($client, app(InsuranceRiskFlags::class));

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('insurance_risk', $run->module->value);
        $this->assertSame(AnalysisLens::values(), $run->framework_lenses);
        $this->assertCount(4, $run->findings);

        $gap = $run->findings->firstWhere('title', 'Insurance coverage gaps');
        $this->assertInstanceOf(AnalysisFinding::class, $gap);
        $this->assertSame('high', $gap->severity->value);
        $this->assertSame(AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED, $gap->document_support);
        $this->assertStringContainsString('Public liability limit is below NZD 1,000,000.', $gap->body);
        $this->assertStringContainsString('Professional indemnity coverage is not evidenced.', $gap->body);
        $this->assertStringContainsString('Insurance certificate appears expired.', $gap->body);
        $this->assertTrue(collect($gap->attributions)->contains(
            fn (array $attribution): bool => $attribution['source_reference'] === "document:{$certificate->id}",
        ));
    }

    private function clientWithInsuranceEvidence(): Client
    {
        $user = User::factory()->create();

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Insurance Risk Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $user->getKey(),
        ]);

        [$questionnaire, $question] = $this->questionnaireWithQuestion();

        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->getKey(),
        ]);

        $response->answers()->create([
            'question_id' => $question->id,
            'value' => 'Insurance certificate shows public liability 500000, expiry 2020-01-01. No professional indemnity or key person cover is supplied.',
            'attached_document_ids' => [],
        ]);

        return $client;
    }

    /**
     * @return array{0: Questionnaire, 1: QuestionnaireQuestion}
     */
    private function questionnaireWithQuestion(): array
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => 'wo52-'.Str::lower(Str::random(8)),
            'title' => 'WO-52 Insurance Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Insurance evidence',
        ]);

        $question = $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::LONG_TEXT,
            'prompt' => 'Summarise insurance coverage, certificate, expiry, policy, public liability, professional indemnity, and key person evidence.',
            'required' => true,
        ]);

        return [$questionnaire, $question];
    }

    private function verifiedInsuranceCertificate(Client $client): Document
    {
        $document = Document::query()->create([
            'client_id' => $client->id,
            'category' => Document::CATEGORY_INSURANCE_CERTIFICATE,
            'original_filename' => 'insurance-certificate.pdf',
            'stored_path' => 'insurance/'.Str::uuid().'.pdf',
            'byte_size' => 2048,
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', $client->id.'insurance'),
            'uploaded_by_user_id' => $client->primary_contact_user_id,
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);

        DocumentVerification::query()->create([
            'document_id' => $document->id,
            'client_id' => $client->id,
            'claim_source' => 'insurance-risk',
            'context_hash' => hash('sha256', $document->id),
            'claim_text' => 'Insurance certificate supports the coverage and expiry evidence.',
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
            'verified_at' => now(),
        ]);

        return $document;
    }
}
