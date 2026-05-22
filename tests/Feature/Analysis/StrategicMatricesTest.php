<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\PvType;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\ImprovementOpportunity;
use App\Models\PvCalculation;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Analysis\Modules\StrategicMatrices;
use App\Services\Analysis\StrategicMatrixAssembler;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StrategicMatricesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_strategic_matrix_assembler_builds_matrices_with_pv_reference(): void
    {
        $client = $this->clientWithStrategicEvidence();
        $opportunity = $this->improvementOpportunity($client);

        $matrix = app(StrategicMatrixAssembler::class)->assemble($client);

        $this->assertArrayHasKey('strengths', $matrix['swot']);
        $this->assertArrayHasKey('wo', $matrix['tows']);
        $this->assertArrayHasKey('priorities', $matrix['maps']);
        $this->assertSame($opportunity->id, $matrix['pv']['top_improvement_id']);
        $this->assertTrue(collect($matrix['attributions'])->contains(
            fn (array $attribution): bool => $attribution['source_reference'] === "improvement_opportunity:{$opportunity->id}",
        ));
    }

    public function test_strategic_matrices_module_runs_with_pv_linked_findings(): void
    {
        $client = $this->clientWithStrategicEvidence();
        $opportunity = $this->improvementOpportunity($client);

        $run = app(AnalysisRunner::class)->run($client, app(StrategicMatrices::class));

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('swot', $run->module->value);
        $this->assertSame(AnalysisLens::values(), $run->framework_lenses);
        $this->assertCount(4, $run->findings);

        $swot = $run->findings->firstWhere('title', 'SWOT matrix');
        $this->assertInstanceOf(AnalysisFinding::class, $swot);
        $this->assertStringContainsString('SWOT:', $swot->body);
        $this->assertTrue(collect($swot->attributions)->contains(
            fn (array $attribution): bool => str_starts_with($attribution['source_reference'], 'questionnaire_answer:'),
        ));

        $priority = $run->findings->firstWhere('lens', AnalysisLens::Prescriptive);
        $this->assertInstanceOf(AnalysisFinding::class, $priority);
        $this->assertSame($opportunity->id, $priority->pv_link_id);
        $this->assertStringContainsString('PV reference', $priority->body);
    }

    private function clientWithStrategicEvidence(): Client
    {
        $user = User::factory()->create();

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Strategic Matrix Fixture Limited',
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
            'value' => 'Strong brand relationships and repeat customers, but manual systems slow delivery. Growth is available through automation and pricing, while competitors and wage pressure are threats.',
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
            'version' => 'wo47-'.Str::lower(Str::random(8)),
            'title' => 'WO-47 Strategic Matrix Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Strategy evidence',
        ]);

        $question = $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::LONG_TEXT,
            'prompt' => 'Summarise strategic strengths, weaknesses, opportunities, and threats.',
            'required' => true,
        ]);

        return [$questionnaire, $question];
    }

    private function improvementOpportunity(Client $client): ImprovementOpportunity
    {
        $calculation = PvCalculation::query()->create([
            'client_id' => $client->getKey(),
            'type' => PvType::ImprovementOpportunity,
            'discount_method' => DiscountMethod::AdvisorConfigured,
            'discount_rate' => 0.12,
            'discount_rate_rationale' => 'Fixture rate.',
            'inputs' => ['cash_flows' => [10000, 10000, 10000]],
            'result' => ['present_value' => 24018.31],
            'as_at' => now(),
            'source_attributions' => [
                ['claim' => 'Fixture improvement PV', 'source_reference' => 'test:strategic-pv'],
            ],
        ]);

        return ImprovementOpportunity::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $calculation->getKey(),
            'title' => 'Automate delivery workflow',
            'annual_benefit' => 10000,
            'duration_years' => 3,
            'pv_of_impact' => 24018.31,
            'rank' => 1,
            'source_attributions' => [
                ['claim' => 'Automation opportunity', 'source_reference' => 'test:automation'],
            ],
        ]);
    }
}
