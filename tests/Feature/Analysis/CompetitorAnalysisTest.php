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
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Analysis\Modules\CompetitorAnalysis;
use App\Services\DataQuality\DataQualityScorer;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CompetitorAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_competitor_module_runs_with_cited_gap_findings(): void
    {
        $client = $this->clientWithCompetitors($this->sevenCompetitors());

        $run = app(AnalysisRunner::class)->run($client, app(CompetitorAnalysis::class));

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('competitor', $run->module->value);
        $this->assertSame(AnalysisLens::values(), $run->framework_lenses);
        $this->assertCount(4, $run->findings);

        $diagnostic = $run->findings->firstWhere('lens', AnalysisLens::Diagnostic);
        $this->assertInstanceOf(AnalysisFinding::class, $diagnostic);
        $this->assertStringContainsString('Pricing evidence is present', $diagnostic->body);
        $this->assertStringContainsString('Visibility evidence is present', $diagnostic->body);
        $this->assertTrue(collect($diagnostic->attributions)->contains(
            fn (array $attribution): bool => str_starts_with($attribution['source_reference'], 'questionnaire_answer:'),
        ));
    }

    public function test_competitor_input_is_limited_to_six_competitors(): void
    {
        $client = $this->clientWithCompetitors($this->sevenCompetitors());
        $module = app(CompetitorAnalysis::class);
        $score = app(DataQualityScorer::class)->score($client);

        $input = $module->promptInput($client, $score);

        $this->assertCount(6, $input['competitors']);
        $this->assertSame('Alpha Advisory', $input['competitors'][0]['name']);
        $this->assertSame('Foxtrot Partners', $input['competitors'][5]['name']);
        $this->assertFalse(collect($input['competitors'])->contains(
            fn (array $competitor): bool => $competitor['name'] === 'Seventh Strategy',
        ));
    }

    /**
     * @param  array<int, string>  $competitors
     */
    private function clientWithCompetitors(array $competitors): Client
    {
        $user = User::factory()->create();

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Competitor Analysis Fixture Limited',
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
            'value' => implode("\n", $competitors),
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
            'version' => 'wo46-'.Str::lower(Str::random(8)),
            'title' => 'WO-46 Competitor Analysis Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Competitors',
        ]);

        $question = $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::LONG_TEXT,
            'prompt' => 'List competitor product, pricing, visibility, and market gap evidence.',
            'required' => true,
        ]);

        return [$questionnaire, $question];
    }

    /**
     * @return array<int, string>
     */
    private function sevenCompetitors(): array
    {
        return [
            'Alpha Advisory - premium pricing and strong Google visibility',
            'Bravo Consulting - cheaper fixed-fee package and narrow product offer',
            'Charlie Growth - strong LinkedIn visibility and advisory service bundles',
            'Delta Finance - discount pricing for small business reviews',
            'Echo Strategy - productised strategy workshops',
            'Foxtrot Partners - high search ranking for NZ advisory terms',
            'Seventh Strategy - should be ignored by the six-competitor bound',
        ];
    }
}
