<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule as AnalysisModuleEnum;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
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
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Exceptions\MissingAttributionException;
use App\Services\Ai\Prompts\PromptRegistry;
use App\Services\Analysis\AnalysisFindingData;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Analysis\Contracts\AnalysisModule;
use App\Services\DataQuality\DataQualityScore;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AnalysisRunnerTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
        $registry = app(PromptRegistry::class);
        $registry->register(
            id: DemoAnalysisModule::PROMPT_ID,
            version: '2026-05-wo31-test',
            body: 'Produce a governed demo analysis from the supplied client facts.',
            task: 'analyse',
        );
        $this->app->instance(PromptRegistry::class, $registry);
    }

    public function test_runner_persists_completed_run_and_governed_findings(): void
    {
        $client = $this->clientWithQuestionnaireResponse();
        $ai = new StructuredAnalysisAiClient;
        $this->app->instance(AiClient::class, $ai);

        $run = app(AnalysisRunner::class)->run($client, new DemoAnalysisModule);

        $this->assertSame(1, $ai->analyseCalls);
        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(AnalysisModuleEnum::Financial, $run->module);
        $this->assertSame('structured-analysis-test', $run->ai_model);
        $this->assertSame(24, $run->tokens_in);
        $this->assertSame(12, $run->tokens_out);
        $this->assertNotNull($run->completed_at);
        $this->assertSame(Client::DATA_QUALITY_LOW, $run->data_quality_snapshot['level']);
        $this->assertSame(AnalysisLens::values(), $run->framework_lenses);

        $findings = $run->findings()->orderBy('lens')->get();
        $this->assertCount(4, $findings);
        $this->assertTrue($findings->every(
            fn (AnalysisFinding $finding): bool => $finding->attributions !== []
                && $finding->document_support === AnalysisFinding::DOCUMENT_SUPPORT_NONE
                && $finding->uncertainty === Uncertainty::Low
                && is_string($finding->data_quality_disclaimer)
                && str_starts_with($finding->data_quality_disclaimer, 'Data quality is Low'),
        ));

        $this->assertDatabaseHas('audit_events', [
            'action' => 'analysis.completed',
            'subject_type' => AnalysisRun::class,
            'subject_id' => $run->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_runner_drops_findings_missing_attribution_without_showing_them(): void
    {
        $client = $this->clientWithQuestionnaireResponse();
        $this->app->instance(AiClient::class, new StructuredAnalysisAiClient);

        $run = app(AnalysisRunner::class)->run($client, new DemoAnalysisModule(includeUnattributedFinding: true));

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(4, $run->findings()->count());
        $this->assertSame(5, $run->metadata['findings_input_count']);
        $this->assertSame(4, $run->metadata['findings_created_count']);
        $this->assertSame(1, data_get($run->metadata, 'dropped_findings.missing_attribution'));
        $this->assertSame('Unattributed finding', data_get($run->metadata, 'dropped_findings.items.0.title'));
        $this->assertSame(AnalysisLens::Diagnostic->value, data_get($run->metadata, 'dropped_findings.items.0.lens'));
        $this->assertSame('missing_attribution', data_get($run->metadata, 'dropped_findings.items.0.reason'));
        $this->assertDatabaseMissing('analysis_findings', [
            'analysis_run_id' => $run->id,
            'title' => 'Unattributed finding',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'analysis.finding_dropped_missing_attribution',
            'subject_type' => AnalysisRun::class,
            'subject_id' => $run->id,
        ]);
    }

    public function test_runner_blocks_insufficient_data_before_calling_ai(): void
    {
        $client = $this->clientWithoutQuestionnaireResponse();
        $ai = new FailingAnalysisAiClient;
        $this->app->instance(AiClient::class, $ai);

        $run = app(AnalysisRunner::class)->run($client, new DemoAnalysisModule);

        $this->assertSame(0, $ai->analyseCalls);
        $this->assertSame(AnalysisRun::STATUS_BLOCKED_DATA_QUALITY, $run->status);
        $this->assertSame(0, $run->findings()->count());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'analysis.blocked_data_quality',
            'subject_type' => AnalysisRun::class,
            'subject_id' => $run->id,
        ]);
    }

    public function test_runner_blocks_both_document_gate_outcomes_before_calling_ai(): void
    {
        foreach ([DocumentVerification::OUTCOME_ADVISORY_FLAG, DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY] as $outcome) {
            $client = $this->clientWithQuestionnaireResponse('Doc Gate '.$outcome);
            $this->blockingVerificationFor($client, $outcome);
            $ai = new FailingAnalysisAiClient;
            $this->app->instance(AiClient::class, $ai);

            $run = app(AnalysisRunner::class)->run($client, new DemoAnalysisModule);

            $this->assertSame(0, $ai->analyseCalls);
            $this->assertSame(AnalysisRun::STATUS_BLOCKED_DOCUMENTS, $run->status);
            $this->assertSame(0, $run->findings()->count());
            $this->assertDatabaseHas('audit_events', [
                'action' => 'analysis.blocked_documents',
                'subject_type' => AnalysisRun::class,
                'subject_id' => $run->id,
            ]);
        }
    }

    public function test_runner_marks_missing_ai_attribution_as_failed(): void
    {
        $client = $this->clientWithQuestionnaireResponse();
        $this->app->instance(AiClient::class, new MissingAttributionAiClient);

        $run = app(AnalysisRunner::class)->run($client, new DemoAnalysisModule);

        $this->assertSame(AnalysisRun::STATUS_FAILED, $run->status);
        $this->assertSame(0, $run->findings()->count());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'analysis.integrity_violation',
            'subject_type' => AnalysisRun::class,
            'subject_id' => $run->id,
        ]);
    }

    public function test_runner_marks_prompt_registration_errors_as_failed_before_calling_ai(): void
    {
        $client = $this->clientWithQuestionnaireResponse();
        $ai = new FailingAnalysisAiClient;
        $this->app->instance(AiClient::class, $ai);

        $run = app(AnalysisRunner::class)->run($client, new UnregisteredPromptAnalysisModule);

        $this->assertSame(0, $ai->analyseCalls);
        $this->assertSame(AnalysisRun::STATUS_FAILED, $run->status);
        $this->assertSame(0, $run->findings()->count());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'analysis.failed',
            'subject_type' => AnalysisRun::class,
            'subject_id' => $run->id,
        ]);
    }

    private function clientWithQuestionnaireResponse(string $name = 'Analysis Spine Test Limited'): Client
    {
        $user = User::factory()->create();
        $client = $this->client($name, $user);
        [$questionnaire, $question] = $this->questionnaireWithQuestion();

        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->getKey(),
        ]);

        $response->answers()->create([
            'question_id' => $question->id,
            'value' => 'Revenue is improving and margin is stable.',
            'attached_document_ids' => [],
        ]);

        return $client;
    }

    private function clientWithoutQuestionnaireResponse(): Client
    {
        return $this->client('Insufficient Analysis Test Limited', User::factory()->create());
    }

    private function client(string $name, User $user): Client
    {
        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
        ]);
    }

    /**
     * @return array{0: Questionnaire, 1: QuestionnaireQuestion}
     */
    private function questionnaireWithQuestion(): array
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => 'wo31-'.Str::lower(Str::random(8)),
            'title' => 'WO-31 Analysis Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Trading',
        ]);

        $question = $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::TEXT,
            'prompt' => 'What is the current trading position?',
            'required' => true,
        ]);

        return [$questionnaire, $question];
    }

    private function blockingVerificationFor(Client $client, string $outcome): void
    {
        $document = Document::query()->create([
            'client_id' => $client->id,
            'category' => Document::CATEGORY_OTHER,
            'original_filename' => $outcome.'.txt',
            'stored_path' => 'analysis-test/'.Str::uuid().'.txt',
            'byte_size' => 10,
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', $outcome),
            'uploaded_by_user_id' => $client->primary_contact_user_id,
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);

        DocumentVerification::query()->create([
            'document_id' => $document->id,
            'client_id' => $client->id,
            'claim_source' => 'analysis-test',
            'context_hash' => hash('sha256', $document->id.$outcome),
            'claim_text' => 'Claim needs review.',
            'outcome' => $outcome,
            'verified_at' => now(),
        ]);
    }
}

final class UnregisteredPromptAnalysisModule implements AnalysisModule
{
    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::Financial;
    }

    public function promptId(): string
    {
        return 'analysis.unregistered';
    }

    public function promptInput(Client $client, DataQualityScore $score): array
    {
        return [];
    }

    public function sourceReferences(Client $client, DataQualityScore $score): array
    {
        return ['test:unregistered-prompt'];
    }

    public function mapFindings(Client $client, AiResponse $response, DataQualityScore $score): array
    {
        return [];
    }
}

final class DemoAnalysisModule implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.demo';

    public function __construct(private readonly bool $includeUnattributedFinding = false) {}

    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::Financial;
    }

    public function promptId(): string
    {
        return self::PROMPT_ID;
    }

    public function promptInput(Client $client, DataQualityScore $score): array
    {
        return [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
            ],
            'data_quality_level' => $score->level,
        ];
    }

    public function sourceReferences(Client $client, DataQualityScore $score): array
    {
        return [
            'questionnaire:wo31-demo',
            'client:'.$client->id,
        ];
    }

    public function mapFindings(Client $client, AiResponse $response, DataQualityScore $score): array
    {
        $findings = [
            new AnalysisFindingData(AnalysisLens::Descriptive, FindingSeverity::Info, 'Descriptive finding', $response->text),
            new AnalysisFindingData(AnalysisLens::Diagnostic, FindingSeverity::Medium, 'Diagnostic finding', $response->text),
            new AnalysisFindingData(AnalysisLens::Predictive, FindingSeverity::Medium, 'Predictive finding', $response->text),
            new AnalysisFindingData(AnalysisLens::Prescriptive, FindingSeverity::Low, 'Prescriptive finding', $response->text),
        ];

        if ($this->includeUnattributedFinding) {
            $findings[] = new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: FindingSeverity::High,
                title: 'Unattributed finding',
                body: 'This finding should never be shown.',
                attributions: [],
            );
        }

        return $findings;
    }
}

class StructuredAnalysisAiClient implements AiClient
{
    public int $analyseCalls = 0;

    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        $this->analyseCalls++;

        return new AiResponse(
            text: 'Client trading evidence shows stable margin and improving revenue.',
            attributions: [
                [
                    'claim' => 'Stable margin and improving revenue.',
                    'source_reference' => 'questionnaire:wo31-demo',
                ],
            ],
            uncertainty: Uncertainty::Low,
            biasSignals: [],
            model: 'structured-analysis-test',
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            tokensIn: 24,
            tokensOut: 12,
            metadata: ['response_id' => 'wo31-test'],
        );
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        return $this->analyse($prompt);
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        return $this->analyse($prompt);
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        return $this->analyse($prompt);
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        return $this->analyse($prompt);
    }
}

final class FailingAnalysisAiClient extends StructuredAnalysisAiClient
{
    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        $this->analyseCalls++;

        throw new \RuntimeException('AI should not be called when the analysis spine is blocked.');
    }
}

final class MissingAttributionAiClient extends StructuredAnalysisAiClient
{
    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        throw new MissingAttributionException('AI response contains text but no source attributions.');
    }
}
