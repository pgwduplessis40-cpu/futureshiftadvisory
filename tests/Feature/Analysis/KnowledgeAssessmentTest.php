<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule as AnalysisModuleEnum;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CoachingSignal;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Prompts\PromptRegistry;
use App\Services\Analysis\AnalysisFindingData;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Analysis\Contracts\AnalysisModule;
use App\Services\Analysis\KnowledgeCalibration;
use App\Services\DataQuality\DataQualityScore;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KnowledgeAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);

        $registry = app(PromptRegistry::class);
        $registry->register(
            id: KnowledgeCalibrationDemoModule::PROMPT_ID,
            version: '2026-05-wo35-test',
            body: 'Produce a governed demo analysis calibrated to the supplied client knowledge profile.',
            task: 'analyse',
        );
        $this->app->instance(PromptRegistry::class, $registry);
    }

    public function test_knowledge_assessment_calibration_is_injected_into_analysis_prompt(): void
    {
        $advisor = $this->advisor();
        $client = $this->clientWithQuestionnaireResponse($advisor);
        app(KnowledgeCalibration::class)->assess(
            client: $client,
            advisor: $advisor,
            financialLiteracy: 1,
            strategicAwareness: 4,
            leadership: 3,
        );
        $ai = new CapturingKnowledgeAiClient;
        $this->app->instance(AiClient::class, $ai);

        $run = app(AnalysisRunner::class)->run($client, new KnowledgeCalibrationDemoModule);

        $this->assertInstanceOf(PromptEnvelope::class, $ai->prompt);
        $calibration = $ai->prompt->input['knowledge_calibration'] ?? null;
        $this->assertIsArray($calibration);
        $this->assertSame('knowledge_assessment', $calibration['source']);
        $this->assertSame('plain_language', $calibration['language_depth']);
        $this->assertSame('explain_terms', $calibration['financial_detail']);
        $this->assertSame('strategic_options', $calibration['strategic_framing']);
        $this->assertSame(1, $calibration['scores']['financial_literacy']);
        $this->assertSame(4, $calibration['scores']['strategic_awareness']);
        $this->assertSame(3, $calibration['scores']['leadership']);
        $this->assertSame($ai->prompt->hash(), $run->prompt_hash);
    }

    public function test_leadership_gap_records_raw_coaching_signal_without_phase_two_action(): void
    {
        $advisor = $this->advisor('leadership-gap@example.test');
        $client = $this->clientFor($advisor, 'Leadership Gap Limited');

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.knowledge-assessments.store', $client), [
                'financial_literacy' => 3,
                'strategic_awareness' => 3,
                'leadership' => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('knowledge_assessments', [
            'client_id' => $client->id,
            'financial_literacy' => 3,
            'strategic_awareness' => 3,
            'leadership' => 2,
        ]);

        $signal = CoachingSignal::query()->firstOrFail();
        $this->assertSame($client->id, $signal->client_id);
        $this->assertSame(CoachingSignal::TYPE_LEADERSHIP_CAPABILITY_GAP, $signal->signal_type);
        $this->assertSame('detected', $signal->status);
        $this->assertSame('knowledge_assessment', $signal->evidence['source']);
        $this->assertSame(2, $signal->evidence['leadership_score']);
        $this->assertTrue($signal->evidence['raw_observation_only']);
        $this->assertFalse($signal->evidence['auto_referral']);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'coaching_signal.raw_observation_recorded',
            'subject_type' => CoachingSignal::class,
            'subject_id' => $signal->id,
            'client_id' => $client->id,
        ]);
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertDatabaseCount('red_flags', 0);
    }

    private function advisor(string $email = 'knowledge-advisor@example.test'): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function clientFor(User $advisor, string $name): Client
    {
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $client;
    }

    private function clientWithQuestionnaireResponse(User $advisor): Client
    {
        $client = $this->clientFor($advisor, 'Knowledge Calibration Limited');
        [$questionnaire, $question] = $this->questionnaireWithQuestion();

        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
            'submitted_by_user_id' => $advisor->getKey(),
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
            'version' => 'wo35-'.Str::lower(Str::random(8)),
            'title' => 'WO-35 Analysis Questionnaire',
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
}

final class KnowledgeCalibrationDemoModule implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.knowledge-calibration-demo';

    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::KnowledgeAssessment;
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
            'questionnaire:wo35-knowledge-calibration',
            'client:'.$client->id,
        ];
    }

    public function mapFindings(Client $client, AiResponse $response, DataQualityScore $score): array
    {
        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: FindingSeverity::Info,
                title: 'Knowledge-calibrated finding',
                body: $response->text,
            ),
        ];
    }
}

final class CapturingKnowledgeAiClient implements AiClient
{
    public ?PromptEnvelope $prompt = null;

    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        $this->prompt = $prompt;

        return new AiResponse(
            text: 'Client trading evidence shows stable margin and improving revenue.',
            attributions: [
                [
                    'claim' => 'Stable margin and improving revenue.',
                    'source_reference' => 'questionnaire:wo35-knowledge-calibration',
                ],
            ],
            uncertainty: Uncertainty::Low,
            biasSignals: [],
            model: 'knowledge-calibration-test',
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            tokensIn: 24,
            tokensOut: 12,
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
