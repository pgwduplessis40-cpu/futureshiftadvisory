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
        $this->assertStringContainsString('Named automation candidates', $diagnostic->body);
        $this->assertStringContainsString('Quantify annual labour cost', $diagnostic->body);
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
        $this->assertStringContainsString('Named systems candidates', $diagnostic->body);
        $this->assertStringContainsString('Quantify duplicate-entry time', $diagnostic->body);
        $this->assertTrue(collect($diagnostic->attributions)->contains(
            fn (array $attribution): bool => str_starts_with($attribution['source_reference'], 'questionnaire_answer:'),
        ));
    }

    public function test_operational_analysis_does_not_flag_positive_no_manual_wording(): void
    {
        $client = $this->clientWithOperationalAndSystemsEvidence(
            operationsValue: 'SOPs are documented and current. No manual tasks. Quote follow-up is already automated. No bottlenecks or delays.',
            systemsValue: 'CRM and inventory systems sync automatically through a working API. No spreadsheets. Current system works.',
        );

        $run = app(AnalysisRunner::class)->run($client, app(OperationalAnalysis::class));

        $diagnostic = $run->findings->firstWhere('title', 'Operational bottleneck diagnosis');
        $this->assertInstanceOf(AnalysisFinding::class, $diagnostic);
        $this->assertStringNotContainsString('Bottleneck evidence is present', $diagnostic->body);
        $this->assertStringNotContainsString('Manual or automation evidence is present', $diagnostic->body);
        $this->assertStringNotContainsString('SOP or handover evidence is present', $diagnostic->body);
        $this->assertStringNotContainsString('Named automation candidates', $diagnostic->body);
        $this->assertStringContainsString('Automation opportunity is not yet evidenced enough to prioritise.', $diagnostic->body);
    }

    public function test_systems_review_does_not_flag_positive_connected_system_wording(): void
    {
        $client = $this->clientWithOperationalAndSystemsEvidence(
            operationsValue: 'SOPs are documented and current. No manual tasks. Quote follow-up is already automated. No bottlenecks or delays.',
            systemsValue: 'CRM and inventory systems sync automatically through a working API. No integration gap. No spreadsheets. Current system works.',
        );

        $run = app(AnalysisRunner::class)->run($client, app(SystemsReview::class));

        $diagnostic = $run->findings->firstWhere('title', 'Systems and integration gaps');
        $this->assertInstanceOf(AnalysisFinding::class, $diagnostic);
        $this->assertStringNotContainsString('Integration-gap evidence is present', $diagnostic->body);
        $this->assertStringNotContainsString('Manual, spreadsheet, duplicate-entry', $diagnostic->body);
        $this->assertStringNotContainsString('Legacy or upgrade evidence is present', $diagnostic->body);
        $this->assertStringNotContainsString('Named systems candidates', $diagnostic->body);
        $this->assertStringContainsString('Integration-gap evidence is not yet specific enough to prioritise.', $diagnostic->body);
    }

    public function test_operational_and_systems_reviews_only_use_standard_advisory_evidence(): void
    {
        $user = User::factory()->create();
        $client = $this->client($user);

        $standardAnswerIds = $this->recordOperationalAndSystemsResponse(
            client: $client,
            user: $user,
            set: QuestionnaireSet::STANDARD_ADVISORY,
            operationsValue: 'SOPs are documented and current. No manual tasks. No bottlenecks or delays.',
            systemsValue: 'CRM and inventory systems sync automatically through a working API. No integration gap. No spreadsheets. Current system works.',
        );
        $dueDiligenceAnswerIds = $this->recordOperationalAndSystemsResponse(
            client: $client,
            user: $user,
            set: QuestionnaireSet::DUE_DILIGENCE,
            operationsValue: 'Dispatch has a bottleneck. SOP handovers are inconsistent and manual spreadsheet rework delays automation.',
            systemsValue: 'CRM and inventory systems do not sync. Teams use duplicate-entry spreadsheets and need an integration upgrade plan.',
        );

        $operationalRun = app(AnalysisRunner::class)->run($client, app(OperationalAnalysis::class));
        $systemsRun = app(AnalysisRunner::class)->run($client, app(SystemsReview::class));

        $operationalDiagnostic = $operationalRun->findings->firstWhere('title', 'Operational bottleneck diagnosis');
        $systemsDiagnostic = $systemsRun->findings->firstWhere('title', 'Systems and integration gaps');
        $this->assertInstanceOf(AnalysisFinding::class, $operationalDiagnostic);
        $this->assertInstanceOf(AnalysisFinding::class, $systemsDiagnostic);
        $this->assertStringNotContainsString('Bottleneck evidence is present', $operationalDiagnostic->body);
        $this->assertStringNotContainsString('Named automation candidates', $operationalDiagnostic->body);
        $this->assertStringNotContainsString('Integration-gap evidence is present', $systemsDiagnostic->body);
        $this->assertStringNotContainsString('Named systems candidates', $systemsDiagnostic->body);

        $sourceReferences = collect([...$operationalDiagnostic->attributions, ...$systemsDiagnostic->attributions])
            ->pluck('source_reference')
            ->all();

        foreach ($standardAnswerIds as $answerId) {
            $this->assertContains("questionnaire_answer:{$answerId}", $sourceReferences);
        }

        foreach ($dueDiligenceAnswerIds as $answerId) {
            $this->assertNotContains("questionnaire_answer:{$answerId}", $sourceReferences);
        }
    }

    private function client(User $user): Client
    {
        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Operations Systems Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $user->getKey(),
        ]);
    }

    /**
     * @return array<int, int|string>
     */
    private function recordOperationalAndSystemsResponse(
        Client $client,
        User $user,
        QuestionnaireSet $set,
        string $operationsValue,
        string $systemsValue,
    ): array {
        [$questionnaire, $questions] = $this->questionnaireWithQuestions($set);

        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->getKey(),
        ]);

        $operationsAnswer = $response->answers()->create([
            'question_id' => $questions['operations']->id,
            'value' => $operationsValue,
            'attached_document_ids' => [],
        ]);
        $systemsAnswer = $response->answers()->create([
            'question_id' => $questions['systems']->id,
            'value' => $systemsValue,
            'attached_document_ids' => [],
        ]);

        return [$operationsAnswer->id, $systemsAnswer->id];
    }

    private function clientWithOperationalAndSystemsEvidence(
        string $operationsValue = 'Order fulfilment has a bottleneck at dispatch. SOP handovers are inconsistent and manual spreadsheet rework delays automation.',
        string $systemsValue = 'CRM and inventory systems do not sync. Teams use duplicate-entry spreadsheets and need an integration upgrade plan.',
    ): Client {
        $user = User::factory()->create();
        $client = $this->client($user);

        $this->recordOperationalAndSystemsResponse(
            client: $client,
            user: $user,
            set: QuestionnaireSet::STANDARD_ADVISORY,
            operationsValue: $operationsValue,
            systemsValue: $systemsValue,
        );

        return $client;
    }

    /**
     * @return array{0: Questionnaire, 1: array{operations: QuestionnaireQuestion, systems: QuestionnaireQuestion}}
     */
    private function questionnaireWithQuestions(QuestionnaireSet $set = QuestionnaireSet::STANDARD_ADVISORY): array
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => $set,
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
