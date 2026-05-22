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
use App\Models\ClientTeamMember;
use App\Models\LearningUpdate;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Notifications\BiasMonitorSignalNotification;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Integrity\BiasDetector;
use App\Services\Ai\Integrity\BiasMonitor;
use App\Services\Ai\Prompts\PromptRegistry;
use App\Services\Analysis\AnalysisFindingData;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Analysis\Contracts\AnalysisModule;
use App\Services\DataQuality\DataQualityScore;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BiasMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);

        $registry = app(PromptRegistry::class);
        $registry->register(
            id: BiasMonitorDemoAnalysisModule::PROMPT_ID,
            version: '2026-05-wo33-test',
            body: 'Produce a governed demo analysis from the supplied client facts.',
            task: 'analyse',
        );
        $this->app->instance(PromptRegistry::class, $registry);
    }

    public function test_analysis_runner_records_bias_signals_for_each_output(): void
    {
        $client = $this->clientWithQuestionnaireResponse();
        $this->app->instance(AiClient::class, new BiasedAnalysisAiClient);

        $run = app(AnalysisRunner::class)->run($client, new BiasMonitorDemoAnalysisModule);
        $finding = $run->findings()->firstOrFail();

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertContains('praise_language', collect($finding->bias_signals)->pluck('type')->all());
        $this->assertContains('risk_suppression_language', collect($finding->bias_signals)->pluck('type')->all());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'ai.bias_assessed',
        ]);
        $this->assertDatabaseMissing('learning_updates', [
            'layer_id' => BiasDetector::LAYER_ID,
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
        $this->assertDatabaseCount('learning_update_implementations', 0);
    }

    public function test_bias_monitor_creates_alerted_governed_candidate_for_systematic_skew(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->create([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);
        $superAdmin->assignRole(User::TYPE_SUPER_ADMIN);

        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        foreach (range(1, 3) as $index) {
            $this->findingForBiasMonitor(
                module: AnalysisModuleEnum::Financial,
                entityType: 'Retail',
                severity: FindingSeverity::High,
                advisor: $index === 1 ? $advisor : null,
            );
        }

        foreach (range(1, 3) as $index) {
            $this->findingForBiasMonitor(
                module: AnalysisModuleEnum::Financial,
                entityType: 'Consulting',
                severity: FindingSeverity::Info,
            );
        }

        $this->artisan('analysis:bias-monitor', [
            '--min-findings' => 3,
            '--skew-threshold' => 0.5,
            '--window-days' => 30,
        ])->assertExitCode(0);

        $candidate = LearningUpdate::query()
            ->where('layer_id', BiasMonitor::LAYER_ID)
            ->where('source->type', 'bias_monitor')
            ->firstOrFail();

        $this->assertSame(LearningUpdate::STATUS_DETECTED, $candidate->status);
        $this->assertSame('bias_monitor', $candidate->source['type']);
        $this->assertSame('entity_type', $candidate->source['dimension']);
        $this->assertSame('Retail', $candidate->source['value']);
        $this->assertSame('review_module_bias_or_calibration', $candidate->proposed_change['action']);
        $this->assertFalse($candidate->proposed_change['automatic_application']);
        $this->assertSame(3, $candidate->clients_affected);
        $this->assertEqualsWithDelta(1.0, (float) $candidate->evidence['cohort_high_rate'], 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $candidate->evidence['baseline_high_rate'], 0.0001);

        $this->assertDatabaseHas('learning_layer_runs', [
            'layer_id' => BiasMonitor::LAYER_ID,
            'status' => 'completed',
            'candidates_created' => 1,
        ]);
        $this->assertDatabaseCount('learning_update_implementations', 0);

        Notification::assertSentTo($superAdmin, BiasMonitorSignalNotification::class);
        Notification::assertSentTo($advisor, BiasMonitorSignalNotification::class);

        $this->artisan('analysis:bias-monitor', [
            '--min-findings' => 3,
            '--skew-threshold' => 0.5,
            '--window-days' => 30,
        ])->assertExitCode(0);

        $this->assertSame(1, LearningUpdate::query()
            ->where('layer_id', BiasMonitor::LAYER_ID)
            ->where('source->type', 'bias_monitor')
            ->count());
        $this->assertDatabaseHas('learning_layer_runs', [
            'layer_id' => BiasMonitor::LAYER_ID,
            'status' => 'completed',
            'candidates_created' => 0,
        ]);
    }

    private function clientWithQuestionnaireResponse(): Client
    {
        $user = User::factory()->create();
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Bias Monitor Analysis Test Limited',
            'entity_type' => 'NZ Limited Company',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
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
            'value' => 'Revenue is improving and margin is stable.',
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
            'version' => 'wo33-'.Str::lower(Str::random(8)),
            'title' => 'WO-33 Analysis Questionnaire',
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

    private function findingForBiasMonitor(
        AnalysisModuleEnum $module,
        string $entityType,
        FindingSeverity $severity,
        ?User $advisor = null,
    ): AnalysisFinding {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Bias Monitor '.fake()->unique()->company(),
            'entity_type' => $entityType,
            'gst_registered' => true,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor?->getKey(),
        ]);

        if ($advisor instanceof User) {
            ClientTeamMember::query()->create([
                'client_id' => $client->id,
                'user_id' => $advisor->getKey(),
                'role' => 'lead_advisor',
                'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
            ]);
        }

        $run = AnalysisRun::query()->create([
            'client_id' => $client->id,
            'module' => $module,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [AnalysisLens::Diagnostic->value],
            'data_quality_snapshot' => ['level' => Client::DATA_QUALITY_LOW],
            'ai_model' => 'bias-monitor-test',
            'prompt_version' => 'wo33-bias-monitor-test',
            'prompt_hash' => hash('sha256', $client->id.$module->value),
            'started_at' => now(),
            'completed_at' => now(),
            'created_by_user_id' => $advisor?->getKey(),
        ]);

        return AnalysisFinding::query()->create([
            'analysis_run_id' => $run->id,
            'client_id' => $client->id,
            'lens' => AnalysisLens::Diagnostic,
            'severity' => $severity,
            'title' => "{$entityType} severity sample",
            'body' => 'Synthetic finding for systematic-skew monitoring.',
            'attributions' => [
                ['claim' => 'Synthetic skew sample', 'source_reference' => 'questionnaire:wo33-bias-monitor'],
            ],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_NONE,
            'uncertainty' => Uncertainty::Medium,
            'bias_signals' => [],
        ]);
    }
}

final class BiasMonitorDemoAnalysisModule implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.bias-monitor-demo';

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
            'questionnaire:wo33-bias-monitor',
            'client:'.$client->id,
        ];
    }

    public function mapFindings(Client $client, AiResponse $response, DataQualityScore $score): array
    {
        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: FindingSeverity::Medium,
                title: 'Bias signal demo finding',
                body: $response->text,
            ),
        ];
    }
}

final class BiasedAnalysisAiClient implements AiClient
{
    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        return new AiResponse(
            text: 'This is an amazing position with no risks based on the supplied trading evidence.',
            attributions: [
                [
                    'claim' => 'Supplied trading evidence supports the finding.',
                    'source_reference' => 'questionnaire:wo33-bias-monitor',
                ],
            ],
            uncertainty: Uncertainty::Low,
            biasSignals: [],
            model: 'biased-analysis-test',
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            tokensIn: 20,
            tokensOut: 10,
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
