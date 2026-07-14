<?php

declare(strict_types=1);

namespace Tests\Feature\StandardAdvisory;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\EngagementType;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\Document;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Analysis\WebsiteUrlConfirmationService;
use App\Services\StandardAdvisory\StandardAdvisoryWorkflow;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StandardAdvisoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_pack_generation_requires_all_standard_advisory_modules(): void
    {
        $client = $this->clientReadyForAnalysis();
        $this->analysisRun($client, AnalysisModule::Financial);

        $partial = app(StandardAdvisoryWorkflow::class)->readiness($client);
        $operationalModule = collect($partial['analysis_modules'])->firstWhere('module', AnalysisModule::Operational->value);

        $this->assertFalse($partial['can_generate_pack']);
        $this->assertSame(1, $partial['analysis_completed']);
        $this->assertFalse($partial['analysis_ready_for_pack']);
        $this->assertSame('analysis_incomplete', $partial['status']);
        $this->assertSame('missing', $operationalModule['status']);
        $this->assertStringContainsString('Operational', implode(' ', $partial['missing']));

        foreach ($this->requiredModulesExcept(AnalysisModule::Financial) as $module) {
            $this->analysisRun($client, $module);
        }

        $ready = app(StandardAdvisoryWorkflow::class)->readiness($client);

        $this->assertTrue($ready['analysis_ready_for_pack']);
        $this->assertTrue($ready['can_generate_pack']);
        $this->assertSame(9, $ready['analysis_completed']);
        $this->assertSame('ready_for_pack', $ready['status']);
    }

    public function test_stale_completed_module_blocks_pack_generation(): void
    {
        $client = $this->clientReadyForAnalysis();

        foreach ($this->requiredModulesExcept() as $module) {
            $this->analysisRun(
                client: $client,
                module: $module,
                completedAt: $module === AnalysisModule::Financial ? now()->subDays(120) : now(),
            );
        }

        $readiness = app(StandardAdvisoryWorkflow::class)->readiness($client);

        $this->assertFalse($readiness['can_generate_pack']);
        $this->assertFalse($readiness['analysis_ready_for_pack']);
        $this->assertSame('analysis_incomplete', $readiness['status']);
        $this->assertSame('stale', collect($readiness['analysis_modules'])->firstWhere('module', AnalysisModule::Financial->value)['status']);
    }

    public function test_failed_module_blocks_until_explicit_pack_waiver_is_recorded(): void
    {
        $client = $this->clientReadyForAnalysis();

        foreach ($this->requiredModulesExcept(AnalysisModule::WebsiteAudit) as $module) {
            $this->analysisRun($client, $module);
        }
        $this->analysisRun(
            client: $client,
            module: AnalysisModule::WebsiteAudit,
            completedAt: now(),
            status: AnalysisRun::STATUS_FAILED,
        );

        $workflow = app(StandardAdvisoryWorkflow::class);
        $blocked = $workflow->readiness($client);

        $this->assertFalse($blocked['can_generate_pack']);
        $this->assertTrue($blocked['can_record_pack_waiver']);
        $this->assertSame('analysis_incomplete', $blocked['status']);
        $this->assertContains(AnalysisModule::WebsiteAudit->value, $blocked['waivable_modules']);
        $this->assertSame('failed', collect($blocked['analysis_modules'])->firstWhere('module', AnalysisModule::WebsiteAudit->value)['status']);

        $actor = User::factory()->create();
        $waiver = $workflow->recordPackWaiver(
            client: $client,
            actor: $actor,
            modules: [AnalysisModule::WebsiteAudit->value],
            reason: 'Advisor has reviewed website evidence manually and is issuing a partial pack for test review.',
        );

        $ready = $workflow->readiness($client);
        $websiteModule = collect($ready['analysis_modules'])->firstWhere('module', AnalysisModule::WebsiteAudit->value);

        $this->assertSame($client->getKey(), $waiver->client_id);
        $this->assertTrue($ready['can_generate_pack']);
        $this->assertTrue($ready['analysis_ready_for_pack']);
        $this->assertSame(8, $ready['analysis_completed']);
        $this->assertSame(1, $ready['analysis_waived']);
        $this->assertSame('ready_for_pack_with_waiver', $ready['status']);
        $this->assertSame('waived', $websiteModule['status']);
        $this->assertSame(AnalysisRun::STATUS_FAILED, $websiteModule['raw_status']);
        $this->assertSame($waiver->getKey(), $websiteModule['waiver']['id']);

        $this->assertDatabaseHas('standard_advisory_pack_waivers', [
            'id' => $waiver->getKey(),
            'client_id' => $client->getKey(),
            'waived_by_user_id' => $actor->getKey(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'standard_advisory.pack_waiver_recorded',
            'client_id' => $client->getKey(),
        ]);
    }

    public function test_readiness_surfaces_dropped_finding_warnings_from_latest_analysis_metadata(): void
    {
        $client = $this->clientReadyForAnalysis();

        foreach ($this->requiredModulesExcept() as $module) {
            $this->analysisRun(
                client: $client,
                module: $module,
                metadata: $module === AnalysisModule::Compliance ? [
                    'dropped_findings' => [
                        'missing_attribution' => 2,
                    ],
                ] : [],
            );
        }

        $readiness = app(StandardAdvisoryWorkflow::class)->readiness($client);
        $compliance = collect($readiness['analysis_modules'])->firstWhere('module', AnalysisModule::Compliance->value);

        $this->assertTrue($readiness['can_generate_pack']);
        $this->assertSame(2, $readiness['analysis_dropped_findings']);
        $this->assertSame(2, $compliance['dropped_findings']['missing_attribution']);
        $this->assertContains(
            '2 analysis finding(s) were dropped because source attribution was incomplete.',
            $readiness['warnings'],
        );
    }

    public function test_pending_client_website_url_blocks_analysis_until_an_advisor_confirms_it(): void
    {
        $client = $this->clientReadyForAnalysis();
        $actor = User::query()->findOrFail($client->primary_contact_user_id);
        $confirmations = app(WebsiteUrlConfirmationService::class);

        $confirmations->submitForAdvisorReview($client, 'https://8.8.8.8/', $actor);

        $blocked = app(StandardAdvisoryWorkflow::class)->readiness($client);

        $this->assertFalse($blocked['can_run_analysis']);
        $this->assertSame('red', $blocked['analysis_readiness']['level']);
        $this->assertStringContainsString('Confirm the client website URL', implode(' ', $blocked['missing']));

        $confirmations->confirm($client, 'https://8.8.8.8/', $actor);

        $ready = app(StandardAdvisoryWorkflow::class)->readiness($client);

        $this->assertTrue($ready['can_run_analysis']);
        $this->assertSame('amber', $ready['analysis_readiness']['level']);
    }

    public function test_submitted_client_onboarding_is_green_for_analysis(): void
    {
        $client = $this->clientReadyForAnalysis();
        $client->forceFill([
            'onboarding_wizard_state' => [
                'journey_version' => 2,
                'submitted_at' => now()->toIso8601String(),
            ],
        ])->save();

        $readiness = app(StandardAdvisoryWorkflow::class)->readiness($client);

        $this->assertTrue($readiness['can_run_analysis']);
        $this->assertSame('green', $readiness['analysis_readiness']['level']);
    }

    public function test_momentum_uses_client_and_advisor_milestones_without_scoring(): void
    {
        $client = $this->clientReadyForAnalysis();
        $client->forceFill([
            'onboarding_wizard_state' => [
                'completed_steps' => ['welcome', 'goals', 'website', 'questionnaire', 'documents', 'review'],
                'submitted_at' => now()->toIso8601String(),
            ],
        ])->save();

        $momentum = app(StandardAdvisoryWorkflow::class)->readiness($client)['momentum'];

        $this->assertSame(5, $momentum['completed']);
        $this->assertSame(7, $momentum['total']);
        $this->assertSame('Website review', $momentum['items'][5]['label']);
        $this->assertSame('advisor', $momentum['items'][5]['owner']);
        $this->assertSame('in_progress', $momentum['items'][5]['status']);
        $this->assertArrayNotHasKey('points', $momentum);
    }

    private function clientReadyForAnalysis(): Client
    {
        $user = User::factory()->create();
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Standard Advisory Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $user->getKey(),
        ]);
        [$questionnaire, $question] = $this->questionnaireWithQuestion();
        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->getKey(),
            'questionnaire_id' => $questionnaire->getKey(),
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->getKey(),
        ]);
        $response->answers()->create([
            'question_id' => $question->getKey(),
            'value' => 'Standard Advisory evidence is complete enough for test readiness.',
            'attached_document_ids' => [],
        ]);

        Document::query()->create([
            'client_id' => $client->getKey(),
            'category' => Document::CATEGORY_OTHER,
            'original_filename' => 'standard-advisory-evidence.txt',
            'stored_path' => 'standard-advisory/'.Str::uuid().'.txt',
            'byte_size' => 120,
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', (string) $client->getKey()),
            'uploaded_by_user_id' => $user->getKey(),
            'scanner_result' => Document::SCANNER_CLEAN,
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
            'version' => 'workflow-'.Str::lower(Str::random(8)),
            'title' => 'Standard Advisory Workflow Test',
            'published_at' => now(),
        ]);
        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Evidence',
        ]);
        $question = $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::TEXT,
            'prompt' => 'What evidence is available?',
            'required' => true,
        ]);

        return [$questionnaire, $question];
    }

    private function analysisRun(
        Client $client,
        AnalysisModule $module,
        mixed $completedAt = null,
        string $status = AnalysisRun::STATUS_COMPLETED,
        array $metadata = [],
    ): AnalysisRun {
        $completedAt ??= now();

        return AnalysisRun::query()->create([
            'client_id' => $client->getKey(),
            'module' => $module,
            'status' => $status,
            'framework_lenses' => [AnalysisLens::Prescriptive->value],
            'data_quality_snapshot' => ['level' => Client::DATA_QUALITY_LOW],
            'metadata' => $metadata,
            'started_at' => $completedAt,
            'completed_at' => $status === AnalysisRun::STATUS_COMPLETED ? $completedAt : null,
        ]);
    }

    /**
     * @return array<int, AnalysisModule>
     */
    private function requiredModulesExcept(?AnalysisModule $except = null): array
    {
        return collect([
            AnalysisModule::Financial,
            AnalysisModule::Operational,
            AnalysisModule::Systems,
            AnalysisModule::Hr,
            AnalysisModule::Swot,
            AnalysisModule::Competitor,
            AnalysisModule::WebsiteAudit,
            AnalysisModule::Compliance,
            AnalysisModule::InsuranceRisk,
        ])
            ->reject(fn (AnalysisModule $module): bool => $except === $module)
            ->values()
            ->all();
    }
}
