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
use App\Models\EconomicIndicator;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Analysis\HolidaysActLiabilityCalculator;
use App\Services\Analysis\Modules\HrAnalysis;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HrAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_holidays_act_liability_calculator_quantifies_remediation(): void
    {
        $liability = app(HolidaysActLiabilityCalculator::class)->calculate(120, 28);

        $this->assertSame(120.0, $liability['underpaid_hours']);
        $this->assertSame(28.0, $liability['hourly_rate']);
        $this->assertSame(3360.0, $liability['gross_liability']);
        $this->assertSame(504.0, $liability['remediation_buffer']);
        $this->assertSame(3864.0, $liability['total_liability']);
    }

    public function test_hr_module_checks_wages_and_cross_references_verified_hr_docs(): void
    {
        $client = $this->clientWithHrEvidence();
        $document = $this->verifiedHrDocument($client);
        $minimumWage = $this->indicator(EconomicIndicator::MINIMUM_WAGE, 'Minimum wage', 23.15);
        $this->indicator(EconomicIndicator::LIVING_WAGE, 'Living wage', 27.8);

        $run = app(AnalysisRunner::class)->run($client, app(HrAnalysis::class));

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('hr', $run->module->value);
        $this->assertSame(AnalysisLens::values(), $run->framework_lenses);
        $this->assertCount(4, $run->findings);

        $wage = $run->findings->firstWhere('title', 'Wage compliance benchmark');
        $this->assertInstanceOf(AnalysisFinding::class, $wage);
        $this->assertStringContainsString('below the current minimum wage benchmark', $wage->body);
        $this->assertSame(AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED, $wage->document_support);
        $this->assertTrue(collect($wage->attributions)->contains(
            fn (array $attribution): bool => $attribution['source_reference'] === "document:{$document->id}",
        ));
        $this->assertTrue(collect($wage->attributions)->contains(
            fn (array $attribution): bool => $attribution['source_reference'] === "economic_indicator:{$minimumWage->id}:minimum_wage",
        ));

        $holidays = $run->findings->firstWhere('title', 'Holidays Act liability exposure');
        $this->assertInstanceOf(AnalysisFinding::class, $holidays);
        $this->assertStringContainsString('NZD 3,864.00', $holidays->body);
    }

    private function clientWithHrEvidence(): Client
    {
        $user = User::factory()->create();

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'HR Analysis Fixture Limited',
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
            'value' => 'Staff structure has 8 employees. Hourly rate 22.00 for junior staff. Holidays Act underpaid 120 hours at 28.00. CV and JD records are uploaded.',
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
            'version' => 'wo48-'.Str::lower(Str::random(8)),
            'title' => 'WO-48 HR Analysis Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'People evidence',
        ]);

        $question = $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::LONG_TEXT,
            'prompt' => 'Summarise HR, people, wage, CV, JD, and Holidays Act evidence.',
            'required' => true,
        ]);

        return [$questionnaire, $question];
    }

    private function verifiedHrDocument(Client $client): Document
    {
        $document = Document::query()->create([
            'client_id' => $client->id,
            'category' => Document::CATEGORY_HR_RECORD,
            'original_filename' => 'staff-structure.pdf',
            'stored_path' => 'hr/'.Str::uuid().'.pdf',
            'byte_size' => 1024,
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', $client->id.'hr'),
            'uploaded_by_user_id' => $client->primary_contact_user_id,
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);

        DocumentVerification::query()->create([
            'document_id' => $document->id,
            'client_id' => $client->id,
            'claim_source' => 'hr-analysis',
            'context_hash' => hash('sha256', $document->id),
            'claim_text' => 'HR document supports staff structure and wage evidence.',
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
            'verified_at' => now(),
        ]);

        return $document;
    }

    private function indicator(string $indicator, string $label, float $value): EconomicIndicator
    {
        return EconomicIndicator::query()->create([
            'indicator' => $indicator,
            'label' => $label,
            'value' => $value,
            'unit' => 'nzd_per_hour',
            'period_date' => '2026-04-01',
            'source' => 'fixture',
            'source_badge' => 'stub',
            'degraded' => false,
            'correlation_id' => null,
            'fetched_at' => now(),
            'payload' => [],
        ]);
    }
}
