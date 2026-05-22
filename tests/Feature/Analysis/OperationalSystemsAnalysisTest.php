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
use App\Services\Analysis\Modules\OperationalAnalysis;
use App\Services\Analysis\Modules\SystemsReview;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OperationalSystemsAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_operational_analysis_runs_with_cited_bottleneck_findings(): void
    {
        $client = $this->clientWithOperationalAndSystemsEvidence();

        $run = app(AnalysisRunner::class)->run($client, app(OperationalAnalysis::class));

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('operational', $run->module->value);
        $this->assertSame(AnalysisLens::values(), $run->framework_lenses);
        $this->assertCount(4, $run->findings);

        $diagnostic = $run->findings->firstWhere('title', 'Operational bottleneck diagnosis');
        $this->assertInstanceOf(AnalysisFinding::class, $diagnostic);
        $this->assertStringContainsString('Bottleneck evidence is present', $diagnostic->body);
        $this->assertStringContainsString('Manual or automation evidence is present', $diagnostic->body);
        $this->assertTrue(collect($diagnostic->attributions)->contains(
            fn (array $attribution): bool => str_starts_with($attribution['source_reference'], 'questionnaire_answer:'),
        ));
    }

    public function test_systems_review_runs_with_cited_integration_findings(): void
    {
        $client = $this->clientWithOperationalAndSystemsEvidence();

        $run = app(AnalysisRunner::class)->run($client, app(SystemsReview::class));

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('systems', $run->module->value);
        $this->assertSame(AnalysisLens::values(), $run->framework_lenses);
        $this->assertCount(4, $run->findings);

        $diagnostic = $run->findings->firstWhere('title', 'Systems and integration gaps');
        $this->assertInstanceOf(AnalysisFinding::class, $diagnostic);
        $this->assertStringContainsString('Integration-gap evidence is present', $diagnostic->body);
        $this->assertStringContainsString('Manual, spreadsheet, duplicate-entry', $diagnostic->body);
        $this->assertTrue(collect($diagnostic->attributions)->contains(
            fn (array $attribution): bool => str_starts_with($attribution['source_reference'], 'questionnaire_answer:'),
        ));
    }

    private function clientWithOperationalAndSystemsEvidence(): Client
    {
        $user = User::factory()->create();

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Operations Systems Fixture Limited',
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
            'question_id' => $questions['operations']->id,
            'value' => 'Order fulfilment has a bottleneck at dispatch. SOP handovers are inconsistent and manual spreadsheet rework delays automation.',
            'attached_document_ids' => [],
        ]);
        $response->answers()->create([
            'question_id' => $questions['systems']->id,
            'value' => 'CRM and inventory systems do not sync. Teams use duplicate-entry spreadsheets and need an integration upgrade plan.',
            'attached_document_ids' => [],
        ]);

        return $client;
    }

    /**
     * @return array{0: Questionnaire, 1: array{operations: QuestionnaireQuestion, systems: QuestionnaireQuestion}}
     */
    private function questionnaireWithQuestions(): array
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => 'wo49-'.Str::lower(Str::random(8)),
            'title' => 'WO-49 Operations Systems Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Operations and systems',
        ]);

        $questions = [
            'operations' => $section->questions()->create([
                'order' => 1,
                'type' => QuestionnaireQuestionType::LONG_TEXT,
                'prompt' => 'Summarise operational process, SOP, bottleneck, workflow, and automation evidence.',
                'required' => true,
            ]),
            'systems' => $section->questions()->create([
                'order' => 2,
                'type' => QuestionnaireQuestionType::LONG_TEXT,
                'prompt' => 'Summarise system, software, integration, CRM, ERP, spreadsheet, and upgrade evidence.',
                'required' => true,
            ]),
        ];

        return [$questionnaire, $questions];
    }
}
