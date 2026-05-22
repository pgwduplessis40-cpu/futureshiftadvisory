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
use App\Models\LearningUpdate;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Analysis\Modules\ComplianceChecker;
use App\Services\Compliance\LegislativeCurrencyMonitor;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ComplianceCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_compliance_checker_rates_severity_with_statute_and_verified_document_citations(): void
    {
        $client = $this->clientWithComplianceEvidence();
        $document = $this->verifiedComplianceDocument($client);

        $run = app(AnalysisRunner::class)->run($client, app(ComplianceChecker::class));

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('compliance', $run->module->value);
        $this->assertSame(AnalysisLens::values(), $run->framework_lenses);
        $this->assertCount(4, $run->findings);

        $diagnostic = $run->findings->firstWhere('title', 'Compliance severity assessment');
        $this->assertInstanceOf(AnalysisFinding::class, $diagnostic);
        $this->assertSame('high', $diagnostic->severity->value);
        $this->assertSame(AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED, $diagnostic->document_support);
        $this->assertStringContainsString('Employment Relations Act evidence is present', $diagnostic->body);
        $this->assertTrue(collect($diagnostic->attributions)->contains(
            fn (array $attribution): bool => $attribution['source_reference'] === 'statute:nz:era',
        ));
        $this->assertTrue(collect($diagnostic->attributions)->contains(
            fn (array $attribution): bool => $attribution['source_reference'] === "document:{$document->id}",
        ));
    }

    public function test_legislative_currency_monitor_queues_governed_candidates_without_auto_apply(): void
    {
        $this->artisan('legislative-currency:monitor', ['--ran-at' => '2026-05-22T09:00:00+12:00'])
            ->expectsOutput('Legislative currency monitor completed with 3 change(s) and 3 candidate(s).')
            ->assertExitCode(0);

        $this->artisan('legislative-currency:monitor', ['--ran-at' => '2026-05-22T10:00:00+12:00'])
            ->expectsOutput('Legislative currency monitor completed with 3 change(s) and 0 candidate(s).')
            ->assertExitCode(0);

        $this->assertDatabaseCount('learning_layer_runs', 2);
        $this->assertDatabaseCount('learning_updates', 3);

        $candidate = LearningUpdate::query()->where('source->change_key', 'era-watch-2026')->firstOrFail();
        $this->assertSame(LegislativeCurrencyMonitor::LAYER_ID, $candidate->layer_id);
        $this->assertSame(LearningUpdate::STATUS_DETECTED, $candidate->status);
        $this->assertSame('review_compliance_checker_statute_currency', $candidate->proposed_change['action']);
        $this->assertFalse($candidate->proposed_change['automatic_application']);
        $this->assertDatabaseCount('learning_update_implementations', 0);
    }

    private function clientWithComplianceEvidence(): Client
    {
        $user = User::factory()->create();

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Compliance Fixture Limited',
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
            'value' => 'Compliance breach risk: employment agreements are missing, H&S policy needs review, payroll holiday settings are unclear, privacy process is incomplete, and director records need Companies Act review.',
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
            'version' => 'wo50-'.Str::lower(Str::random(8)),
            'title' => 'WO-50 Compliance Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Compliance evidence',
        ]);

        $question = $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::LONG_TEXT,
            'prompt' => 'Summarise compliance, employment agreement, H&S, Holidays Act/payroll, privacy, Companies Act, and director evidence.',
            'required' => true,
        ]);

        return [$questionnaire, $question];
    }

    private function verifiedComplianceDocument(Client $client): Document
    {
        $document = Document::query()->create([
            'client_id' => $client->id,
            'category' => Document::CATEGORY_COMPLIANCE_DOC,
            'original_filename' => 'hs-policy.pdf',
            'stored_path' => 'compliance/'.Str::uuid().'.pdf',
            'byte_size' => 1024,
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', $client->id.'compliance'),
            'uploaded_by_user_id' => $client->primary_contact_user_id,
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);

        DocumentVerification::query()->create([
            'document_id' => $document->id,
            'client_id' => $client->id,
            'claim_source' => 'compliance-checker',
            'context_hash' => hash('sha256', $document->id),
            'claim_text' => 'Compliance document supports the H&S policy evidence.',
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
            'verified_at' => now(),
        ]);

        return $document;
    }
}
